<?php

namespace Tests\Feature;

use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use App\Modules\User\Models\User;
use App\Modules\Wallet\Models\WalletTransaction;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
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

    public function test_vietqr_reconciliation_is_read_only_and_cannot_mark_pending_orders_paid(): void
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

        $this->withoutMiddleware();
        $response = $this->postJson('/api/v1/admin/payment-reconciliations', [
            'provider' => 'vietqr', 'payment_reference' => 'HS-QR-REFERENCE-001',
            'bank_reference' => 'BANK-QR-001', 'amount' => 500_000,
            'evidence_note' => 'Sandbox test data only; no bank transfer.',
            'verified_against_bank_statement' => true,
        ]);

        $response->assertStatus(410)->assertJsonPath('status', 'error');
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame(Order::STATUS_PENDING_PAYMENT, $order->fresh()->status);
        $this->assertSame(Product::STATUS_RESERVED, $product->fresh()->status);
        $this->assertSame(0, WalletTransaction::where('reference', 'ORDER_ESCROW_'.$order->id)->count());
    }
}
