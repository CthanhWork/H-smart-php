<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderDispute;
use App\Modules\Product\Models\Product;
use App\Modules\Wallet\Models\WalletEntry;
use App\Modules\Wallet\Models\WalletVnPayDeposit;
use App\Modules\Wallet\Services\WalletLedger;
use App\Modules\Wallet\Services\WalletVnPayService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class WalletVnPayMysqlLockTest extends TestCase
{
    public function createApplication()
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    public function test_mysql_row_lock_serializes_two_concurrent_signed_callbacks(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Run with DB_CONNECTION=mysql to exercise MySQL row locks.');
        }

        config([
            'services.vnpay.mode' => 'gateway', 'services.vnpay.tmn_code' => 'MYSQLTEST',
            'services.vnpay.hash_secret' => 'mysql-lock-test-secret',
        ]);
        $suffix = Str::lower(Str::random(12));
        $user = User::create(['username' => 'vnpay_'.$suffix, 'email' => $suffix.'@example.test', 'password_hash' => 'unused']);
        $deposit = WalletVnPayDeposit::create([
            'user_id' => $user->id, 'reference' => 'HWS'.$suffix,
            'idempotency_key' => 'mysql-topup-'.$suffix, 'amount' => 100_000,
            'provider' => 'vnpay', 'mode' => 'sandbox', 'status' => 'pending', 'expires_at' => now()->addMinutes(5),
        ]);

        try {
            $query = [
                'vnp_Amount' => '10000000', 'vnp_BankCode' => 'TESTBANK', 'vnp_CurrCode' => 'VND',
                'vnp_ResponseCode' => '00', 'vnp_TmnCode' => 'MYSQLTEST', 'vnp_TransactionNo' => 'TXN-'.$suffix,
                'vnp_TransactionStatus' => '00', 'vnp_TxnRef' => $deposit->reference,
            ];
            ksort($query);
            $query['vnp_SecureHash'] = hash_hmac('sha512', http_build_query($query, '', '&', PHP_QUERY_RFC1738), 'mysql-lock-test-secret');
            $payload = base64_encode(json_encode([
                'query' => $query, 'tmn_code' => 'MYSQLTEST', 'hash_secret' => 'mysql-lock-test-secret',
                'database_default' => config('database.default'),
                'database_connection' => config('database.connections.'.config('database.default')),
            ], JSON_THROW_ON_ERROR));
            $workerPath = __DIR__.'/../Fixtures/wallet_vnpay_callback_worker.php';
            $first = new Process([PHP_BINARY, $workerPath, $payload]);
            $second = new Process([PHP_BINARY, $workerPath, $payload]);
            $first->start();
            $second->start();
            $first->wait();
            $second->wait();

            $this->assertTrue($first->isSuccessful(), $first->getErrorOutput().' '.$first->getOutput());
            $this->assertTrue($second->isSuccessful(), $second->getErrorOutput().' '.$second->getOutput());
            $this->assertEqualsCanonicalizing(['newly_paid', 'success'], [$first->getOutput(), $second->getOutput()]);
            $this->assertSame(100_000, app(WalletLedger::class)->balance((int) $user->id)['available']);
            $this->assertSame(1, DB::table('wallet_transactions')->where('reference', 'VNPAY_WALLET_'.$deposit->reference)->count());
            $this->assertSame(2, WalletEntry::query()->where('wallet_transaction_id', $deposit->fresh()->wallet_transaction_id)->count());
        } finally {
            $transactionId = $deposit->fresh()?->wallet_transaction_id;
            if ($transactionId) {
                DB::table('wallet_entries')->where('wallet_transaction_id', $transactionId)->delete();
                DB::table('wallet_transactions')->where('id', $transactionId)->delete();
            }
            $deposit->delete();
            DB::table('wallet_accounts')->where('user_id', $user->id)->delete();
            $user->delete();
        }
    }

    public function test_mysql_dispute_and_release_scheduler_race_keeps_order_held(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Run with DB_CONNECTION=mysql to exercise MySQL row locks.');
        }

        $suffix = Str::lower(Str::random(12));
        $admin = User::create(['username' => 'admin_'.$suffix, 'email' => $suffix.'-admin@example.test', 'password_hash' => 'unused', 'role' => 'admin']);
        $buyer = User::create(['username' => 'buyer_'.$suffix, 'email' => $suffix.'-buyer@example.test', 'password_hash' => 'unused']);
        $seller = User::create(['username' => 'seller_'.$suffix, 'email' => $suffix.'-seller@example.test', 'password_hash' => 'unused']);
        $product = Product::create(['seller_id' => $seller->id, 'title' => 'MySQL dispute race', 'price' => 100_000, 'condition' => 'good', 'status' => 'sold']);
        $order = Order::create([
            'buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'product_id' => $product->id,
            'product_amount' => 100_000, 'fee_base' => 100_000, 'platform_fee' => 2_000,
            'commission_rate' => 2, 'total_amount' => 100_000, 'status' => 'completed',
            'payment_status' => 'paid', 'payment_method' => 'vnpay', 'payment_provider' => 'vnpay_mock',
            'payment_amount' => 100_000, 'payment_paid_at' => now(), 'paid_at' => now(),
            'delivered_at' => now()->subHours(49), 'completed_at' => now(), 'release_eligible_at' => now(),
            'payout_status' => 'eligible', 'shipping_address_snapshot' => ['recipient_name' => 'B', 'phone' => '0900000000', 'address' => 'Test'],
            'expires_at' => now()->addDay(),
        ]);
        $dispute = OrderDispute::create([
            'order_id' => $order->id, 'opened_by' => $buyer->id, 'status' => 'open',
            'reason' => 'Khiếu nại QA', 'details' => 'Kiểm tra khóa cạnh tranh MySQL.',
        ]);
        app(WalletLedger::class)->bookOrderEscrow($order, 'vnpay_mock');
        $payload = base64_encode(json_encode([
            'mode' => 'race', 'database_default' => config('database.default'),
            'database_connection' => config('database.connections.'.config('database.default')),
            'admin_id' => $admin->id, 'dispute_id' => $dispute->id,
        ], JSON_THROW_ON_ERROR));

        try {
            $workerPath = __DIR__.'/../Fixtures/wallet_vnpay_callback_worker.php';
            $release = new Process([PHP_BINARY, $workerPath, $payload, 'release']);
            $resolve = new Process([PHP_BINARY, $workerPath, $payload, 'resolve']);
            $release->start();
            $resolve->start();
            $release->wait();
            $resolve->wait();

            $this->assertTrue($release->isSuccessful(), $release->getErrorOutput().' '.$release->getOutput());
            $this->assertTrue($resolve->isSuccessful(), $resolve->getErrorOutput().' '.$resolve->getOutput());
            $this->assertSame('0', trim($release->getOutput()));
            $this->assertSame('reconciliation_pending', trim($resolve->getOutput()));
            $this->assertSame('held', $order->fresh()->payout_status);
            $this->assertSame('reconciliation_pending', $dispute->fresh()->status);
            $this->assertSame(0, app(WalletLedger::class)->balance((int) $seller->id)['available']);
            $this->assertSame(98_000, app(WalletLedger::class)->balance((int) $seller->id)['pending_release']);
            $this->assertSame(0, DB::table('wallet_transactions')->where('reference', 'ORDER_RELEASE_'.$order->id)->count());
        } finally {
            $transactionIds = DB::table('wallet_transactions')->where('source_type', 'order')->where('source_id', $order->id)->pluck('id');
            DB::table('wallet_entries')->whereIn('wallet_transaction_id', $transactionIds)->delete();
            DB::table('wallet_transactions')->whereIn('id', $transactionIds)->delete();
            DB::table('notifications')->whereIn('recipient_id', [$buyer->id, $seller->id])->delete();
            DB::table('admin_audit_logs')->where('subject_type', 'order_dispute')->where('subject_id', $dispute->id)->delete();
            DB::table('order_dispute_events')->where('dispute_id', $dispute->id)->delete();
            DB::table('order_status_history')->where('order_id', $order->id)->delete();
            $dispute->delete();
            $order->delete();
            $product->delete();
            DB::table('wallet_accounts')->whereIn('user_id', [$admin->id, $buyer->id, $seller->id])->delete();
            User::whereIn('id', [$admin->id, $buyer->id, $seller->id])->delete();
        }
    }
}
