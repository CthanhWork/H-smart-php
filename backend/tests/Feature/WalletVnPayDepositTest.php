<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Modules\Order\Services\VnPayService;
use App\Modules\Wallet\Models\WalletEntry;
use App\Modules\Wallet\Models\WalletVnPayDeposit;
use App\Modules\Wallet\Services\WalletLedger;
use App\Modules\Wallet\Services\WalletVnPayService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;

class WalletVnPayDepositTest extends TestCase
{
    public function createApplication()
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance('env', 'testing');
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('v', 32)),
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'session.driver' => 'array',
            'cache.default' => 'array',
            'services.vnpay.mode' => 'gateway',
            'services.vnpay.tmn_code' => 'TESTTMN',
            'services.vnpay.hash_secret' => 'test-secret',
            'services.vnpay.payment_url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
        ]);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    }

    public function test_signed_callback_credits_once_and_wrong_signature_is_ignored(): void
    {
        $deposit = $this->deposit();
        $service = app(WalletVnPayService::class);
        $callback = $this->signedCallback($deposit->reference, (int) $deposit->amount);
        $badSignature = $callback;
        $badSignature['vnp_SecureHash'] = str_repeat('0', 128);

        $this->assertSame('invalid', $service->processCallback($badSignature));
        $this->assertSame('newly_paid', $service->processCallback($callback));
        $this->assertSame('success', $service->processCallback($callback));
        $this->assertSame('paid', $deposit->fresh()->status);
        $this->assertSame(1, DB::table('wallet_transactions')->where('reference', 'VNPAY_WALLET_'.$deposit->reference)->count());
        $this->assertSame(100_000, app(WalletLedger::class)->balance((int) $deposit->user_id)['available']);
        $this->assertSame(2, WalletEntry::query()->where('wallet_transaction_id', $deposit->fresh()->wallet_transaction_id)->count());
    }

    public function test_amount_mismatch_and_late_or_closed_callbacks_are_reconciled_without_credit(): void
    {
        $service = app(WalletVnPayService::class);
        $mismatch = $this->deposit('HWS-MISMATCH');
        $this->assertSame('amount_mismatch', $service->processCallback($this->signedCallback($mismatch->reference, 99_999)));
        $this->assertSame('reconciliation_required', $mismatch->fresh()->status);

        $late = $this->deposit('HWS-LATE', expiresAt: now()->subMinute());
        $this->assertSame('late', $service->processCallback($this->signedCallback($late->reference, (int) $late->amount)));
        $this->assertSame('reconciliation_required', $late->fresh()->status);

        $closed = $this->deposit('HWS-CLOSED', status: 'failed');
        $this->assertSame('late', $service->processCallback($this->signedCallback($closed->reference, (int) $closed->amount)));
        $this->assertSame('reconciliation_required', $closed->fresh()->status);
        $this->assertSame(0, WalletEntry::query()->count());
    }

    public function test_wallet_reference_is_not_accepted_as_an_order_callback(): void
    {
        $deposit = $this->deposit();
        $signedWalletCallback = $this->signedCallback($deposit->reference, (int) $deposit->amount);
        $this->assertSame('invalid', app(VnPayService::class)->processIpn($signedWalletCallback));
        $this->assertSame('pending', $deposit->fresh()->status);

        $signedOrderCallback = $this->signedCallback('HS123ORDERREFERENCE', (int) $deposit->amount);
        $this->assertSame('invalid', app(WalletVnPayService::class)->processCallback($signedOrderCallback));
    }

    public function test_shared_merchant_ipn_route_dispatches_wallet_reference_to_wallet_ledger(): void
    {
        $deposit = $this->deposit();
        $query = http_build_query($this->signedCallback($deposit->reference, (int) $deposit->amount));

        $this->get('/api/v1/payments/vnpay/ipn?'.$query)->assertOk()->assertJson(['RspCode' => '00']);
        $this->get('/api/v1/payments/vnpay/ipn?'.$query)->assertOk()->assertJson(['RspCode' => '00']);

        $this->assertSame(100_000, app(WalletLedger::class)->balance((int) $deposit->user_id)['available']);
        $this->assertSame(1, DB::table('wallet_transactions')->where('reference', 'VNPAY_WALLET_'.$deposit->reference)->count());
    }

    private function deposit(string $reference = 'HWS-TEST-REFERENCE', ?string $status = null, $expiresAt = null): WalletVnPayDeposit
    {
        static $sequence = 0;
        $sequence++;
        $user = User::create(['username' => 'wallet_user_'.$sequence, 'email' => 'wallet'.$sequence.'@example.test', 'password_hash' => 'unused']);

        return WalletVnPayDeposit::create([
            'user_id' => $user->id,
            'reference' => $reference,
            'idempotency_key' => 'wallet-topup-key-'.$sequence,
            'amount' => 100_000,
            'provider' => 'vnpay',
            'mode' => 'sandbox',
            'status' => $status ?? 'pending',
            'expires_at' => $expiresAt ?? now()->addMinutes(15),
        ]);
    }

    private function signedCallback(string $reference, int $amount): array
    {
        $query = [
            'vnp_Amount' => (string) ($amount * 100),
            'vnp_BankCode' => 'TESTBANK',
            'vnp_CurrCode' => 'VND',
            'vnp_ResponseCode' => '00',
            'vnp_TmnCode' => 'TESTTMN',
            'vnp_TransactionNo' => 'TXN-'.$reference,
            'vnp_TransactionStatus' => '00',
            'vnp_TxnRef' => $reference,
        ];
        ksort($query);
        $query['vnp_SecureHash'] = hash_hmac('sha512', http_build_query($query, '', '&', PHP_QUERY_RFC1738), 'test-secret');

        return $query;
    }
}
