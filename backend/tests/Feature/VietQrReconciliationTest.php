<?php

namespace Tests\Feature;

use App\Modules\Admin\Http\Controllers\AdminPaymentReconciliationController;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\PaymentReconciliation;
use App\Modules\Product\Models\Product;
use App\Modules\User\Models\User;
use App\Modules\Wallet\Models\WalletTransaction;
use App\Modules\Wallet\Services\WalletLedger;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VietQrReconciliationTest extends TestCase
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

    private function pendingVietQrOrder(): array
    {
        $buyer = User::create(['username' => 'qr-buyer', 'email' => 'qr-buyer@example.test', 'password_hash' => 'unused']);
        $seller = User::create(['username' => 'qr-seller', 'email' => 'qr-seller@example.test', 'password_hash' => 'unused']);
        $product = Product::create(['seller_id' => $seller->id, 'title' => 'Sản phẩm VietQR', 'price' => 500_000, 'condition' => 'good', 'status' => Product::STATUS_RESERVED]);
        $order = Order::create([
            'product_id' => $product->id, 'buyer_id' => $buyer->id, 'seller_id' => $seller->id,
            'product_amount' => 500_000, 'shipping_fee' => 0, 'fee_base' => 500_000,
            'platform_fee' => 10_000, 'commission_rate' => 2, 'total_amount' => 500_000,
            'status' => Order::STATUS_PENDING_PAYMENT, 'shipping_address_snapshot' => [], 'expires_at' => now()->addMinutes(30),
            'payment_provider' => 'vietqr', 'payment_method' => 'vnpay', 'payment_txn_ref' => 'HS-QR-REFERENCE-001',
            'payment_amount' => 500_000, 'payment_status' => 'pending',
        ]);

        return [$buyer, $seller, $product, $order];
    }

    private function reconcile(User $admin, string $bankReference, int $amount): array
    {
        $request = Request::create('/api/v1/admin/payment-reconciliations', 'POST', [
            'provider' => 'vietqr', 'payment_reference' => 'HS-QR-REFERENCE-001',
            'bank_reference' => $bankReference, 'amount' => $amount,
            'evidence_note' => 'Đã kiểm tra sao kê ngân hàng thử nghiệm.',
            'verified_against_bank_statement' => true,
        ]);
        $request->setUserResolver(fn () => $admin);

        return app(AdminPaymentReconciliationController::class)
            ->store($request, app(WalletLedger::class), app(AdminAuditLogger::class))
            ->getData(true);
    }

    public function test_exact_verified_transfer_pays_once_and_books_escrow_once(): void
    {
        [, , $product, $order] = $this->pendingVietQrOrder();
        $admin = User::create(['username' => 'qr-admin', 'email' => 'qr-admin@example.test', 'password_hash' => 'unused', 'role' => 'admin']);

        $response = $this->reconcile($admin, 'BANK-QR-001', 500_000);
        $this->assertSame('matched', $response['data']['status']);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(Product::STATUS_SOLD, $product->fresh()->status);
        $this->assertSame(1, WalletTransaction::where('reference', 'ORDER_ESCROW_'.$order->id)->count());

        $repeat = $this->reconcile($admin, 'BANK-QR-001-REPLAY', 500_000);
        $this->assertSame(1, PaymentReconciliation::where('bank_reference', 'BANK-QR-001')->count());
        $this->assertSame(1, WalletTransaction::where('reference', 'ORDER_ESCROW_'.$order->id)->count());
        $this->assertSame('not_payable', $repeat['data']['status']);
    }

    public function test_amount_mismatch_is_recorded_without_marking_order_paid(): void
    {
        [, , $product, $order] = $this->pendingVietQrOrder();
        $admin = User::create(['username' => 'qr-admin', 'email' => 'qr-admin@example.test', 'password_hash' => 'unused', 'role' => 'admin']);

        $response = $this->reconcile($admin, 'BANK-QR-002', 499_000);

        $this->assertSame('amount_mismatch', $response['data']['status']);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame(Product::STATUS_RESERVED, $product->fresh()->status);
        $this->assertSame(0, WalletTransaction::where('reference', 'ORDER_ESCROW_'.$order->id)->count());
    }

    public function test_late_transfer_is_recorded_without_reviving_expired_order(): void
    {
        [, , $product, $order] = $this->pendingVietQrOrder();
        $order->update(['expires_at' => now()->subMinute()]);
        $admin = User::create(['username' => 'qr-admin', 'email' => 'qr-admin@example.test', 'password_hash' => 'unused', 'role' => 'admin']);

        $response = $this->reconcile($admin, 'BANK-QR-LATE-001', 500_000);

        $this->assertSame('late', $response['data']['status']);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame(Order::STATUS_PENDING_PAYMENT, $order->fresh()->status);
        $this->assertSame(Product::STATUS_RESERVED, $product->fresh()->status);
        $this->assertSame(0, WalletTransaction::where('reference', 'ORDER_ESCROW_'.$order->id)->count());
    }
}
