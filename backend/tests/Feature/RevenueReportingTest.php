<?php

namespace Tests\Feature;

use App\Modules\Admin\Http\Controllers\AdminRevenueController;
use App\Modules\Product\Models\Product;
use App\Modules\User\Models\User;
use App\Modules\Wallet\Http\Controllers\SellerRevenueController;
use App\Support\Reporting\RevenuePeriod;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RevenueReportingTest extends TestCase
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
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    }

    public function test_seller_totals_are_scoped_to_signed_in_seller_and_admin_totals_use_ledger(): void
    {
        $seller = $this->user('seller');
        $otherSeller = $this->user('other');
        $buyer = $this->user('buyer');
        $admin = $this->user('admin', 'admin');
        $product = Product::create(['seller_id' => $seller->id, 'title' => 'Đơn của người bán', 'price' => 100_000, 'condition' => 'good', 'status' => 'sold']);
        $otherProduct = Product::create(['seller_id' => $otherSeller->id, 'title' => 'Đơn người bán khác', 'price' => 900_000, 'condition' => 'good', 'status' => 'sold']);
        $orderId = $this->insertOrder($seller, $buyer, $product, 100_000, 2_000);
        $this->insertOrder($otherSeller, $buyer, $otherProduct, 900_000, 18_000);
        $this->walletTransaction('REPORT_ORDER_ESCROW', 'order_escrow_received', 'order', $orderId, [
            ['user_id' => $seller->id, 'account_code' => 'pending_release', 'amount' => 98_000],
            ['user_id' => null, 'account_code' => 'platform_revenue', 'amount' => 2_000],
            ['user_id' => null, 'account_code' => 'provider_clearing', 'amount' => -100_000],
        ]);
        $this->walletTransaction('REPORT_ORDER_REFUND', 'order_refund_to_wallet', 'order', $orderId, [
            ['user_id' => $seller->id, 'account_code' => 'pending_release', 'amount' => -98_000],
            ['user_id' => $buyer->id, 'account_code' => 'available', 'amount' => 100_000],
            ['user_id' => null, 'account_code' => 'platform_revenue', 'amount' => -2_000],
        ]);
        $this->platformEntry(null, 'listing_fee', 5_000, 'product', (int) $product->id);
        $this->platformEntry(null, 'product_promotion', 15_000, 'product', (int) $product->id);
        $sellerRequest = Request::create('/api/v1/me/wallet/revenue', 'GET', ['period' => 'this_month']);
        $sellerRequest->setUserResolver(fn () => $seller);
        $sellerReport = app(SellerRevenueController::class)($sellerRequest, app(RevenuePeriod::class))->getData(true)['data'];

        $adminRequest = Request::create('/api/v1/admin/revenue', 'GET', ['period' => 'this_month']);
        $adminRequest->setUserResolver(fn () => $admin);
        $adminReport = app(AdminRevenueController::class)($adminRequest, app(RevenuePeriod::class))->getData(true)['data'];

        $this->assertSame(100_000, $sellerReport['metrics']['completed_product_amount']);
        $this->assertSame(2_000, $sellerReport['metrics']['commission_amount']);
        $this->assertSame(98_000, $sellerReport['metrics']['refund_amount']);
        $this->assertSame(0, $sellerReport['metrics']['net_sales_revenue']);
        $this->assertSame(1, $sellerReport['metrics']['completed_orders']);
        $this->assertSame(1, $sellerReport['metrics']['refunded_orders']);
        $this->assertSame(2, $sellerReport['details']['total']);
        $this->assertSame(1_000_000, $adminReport['metrics']['paid_goods_value']);
        $this->assertSame(2_000, $adminReport['metrics']['commission_revenue']);
        $this->assertSame(5_000, $adminReport['metrics']['listing_fee_revenue']);
        $this->assertSame(15_000, $adminReport['metrics']['promotion_revenue']);
        $this->assertSame(-2_000, $adminReport['metrics']['revenue_reversals']);
        $this->assertSame(20_000, $adminReport['metrics']['net_platform_revenue']);
        $this->assertStringContainsString('Sandbox/mô phỏng', $adminReport['mode']);
    }

    public function test_custom_date_period_uses_vietnam_timezone_and_exclusive_next_day_boundary(): void
    {
        $request = Request::create('/report', 'GET', ['period' => 'custom', 'from' => '2026-11-01', 'to' => '2026-11-01']);
        $range = app(RevenuePeriod::class)->resolve($request);

        $this->assertSame('Asia/Ho_Chi_Minh', $range['timezone']);
        $this->assertSame('2026-10-31 17:00:00', $range['from_utc']);
        $this->assertSame('2026-11-01 17:00:00', $range['to_utc_exclusive']);
    }

    private function user(string $name, string $role = 'member'): User
    {
        return User::create(['username' => $name, 'email' => $name.'@example.test', 'password_hash' => 'unused', 'role' => $role]);
    }

    private function insertOrder(User $seller, User $buyer, Product $product, int $amount, int $fee): int
    {
        return (int) DB::table('orders')->insertGetId([
            'product_id' => $product->id, 'buyer_id' => $buyer->id, 'seller_id' => $seller->id,
            'product_amount' => $amount, 'shipping_fee' => 0, 'platform_fee' => $fee,
            'total_amount' => $amount, 'status' => 'completed', 'payment_status' => 'paid',
            'payment_method' => 'vnpay', 'payment_provider' => 'vnpay_mock', 'payment_amount' => $amount,
            'payment_paid_at' => now(), 'completed_at' => now(), 'shipping_address_snapshot' => json_encode(['recipient_name' => 'Buyer']),
            'expires_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function walletTransaction(string $reference, string $type, string $sourceType, int $sourceId, array $entries): void
    {
        $transactionId = DB::table('wallet_transactions')->insertGetId([
            'reference' => $reference, 'idempotency_key' => $reference, 'type' => $type,
            'status' => 'posted', 'source_type' => $sourceType, 'source_id' => $sourceId,
            'metadata' => json_encode(['mode' => 'simulation']), 'posted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('wallet_entries')->insert(array_map(fn (array $entry) => [
            ...$entry, 'wallet_transaction_id' => $transactionId, 'created_at' => now(), 'updated_at' => now(),
        ], $entries));
    }

    private function platformEntry(?int $userId, string $type, int $amount, string $sourceType, int $sourceId): void
    {
        $reference = 'REPORT_'.$type;
        if ($type === 'order_escrow_received') $reference .= '_'.$sourceId;
        $transactionId = DB::table('wallet_transactions')->insertGetId([
            'reference' => $reference, 'idempotency_key' => $reference, 'type' => $type,
            'status' => 'posted', 'source_type' => $sourceType, 'source_id' => $sourceId,
            'metadata' => json_encode(['mode' => 'simulation']), 'posted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('wallet_entries')->insert([
            ['wallet_transaction_id' => $transactionId, 'user_id' => $userId, 'account_code' => 'platform_revenue', 'amount' => $amount, 'created_at' => now(), 'updated_at' => now()],
            ['wallet_transaction_id' => $transactionId, 'user_id' => null, 'account_code' => 'provider_clearing', 'amount' => -$amount, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
