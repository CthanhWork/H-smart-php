<?php

namespace Tests\Feature;

use App\Modules\Notification\Services\NotificationService;
use App\Modules\Offer\Models\Offer;
use App\Modules\Order\Actions\CreateOrderAction;
use App\Modules\Order\Actions\ExpirePendingOrdersAction;
use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use App\Modules\User\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class LegacyVietQrOrderSafetyTest extends TestCase
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

    public function test_expired_legacy_vietqr_order_stays_reserved_and_cannot_be_released_by_retry_paths(): void
    {
        $buyer = User::create(['username' => 'legacy-qr-buyer', 'email' => 'legacy-qr-buyer@example.test', 'password_hash' => 'unused']);
        $seller = User::create(['username' => 'legacy-qr-seller', 'email' => 'legacy-qr-seller@example.test', 'password_hash' => 'unused']);
        $product = Product::create(['seller_id' => $seller->id, 'title' => 'Sản phẩm có yêu cầu VietQR cũ', 'price' => 100_000, 'condition' => 'good', 'status' => Product::STATUS_RESERVED]);
        $offer = Offer::create([
            'product_id' => $product->id, 'buyer_id' => $buyer->id, 'seller_id' => $seller->id,
            'offer_price' => 100_000, 'status' => Offer::STATUS_ACCEPTED, 'expires_at' => now()->addHour(),
        ]);
        $order = Order::create([
            'product_id' => $product->id, 'buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'offer_id' => $offer->id,
            'product_amount' => 100_000, 'shipping_fee' => 0, 'fee_base' => 100_000,
            'platform_fee' => 2_000, 'commission_rate' => 2, 'total_amount' => 100_000,
            'status' => Order::STATUS_PENDING_PAYMENT, 'shipping_address_snapshot' => [], 'expires_at' => now()->subMinute(),
            'payment_provider' => 'vietqr', 'payment_method' => 'vnpay', 'payment_txn_ref' => 'OLD-QR-REF-0001',
            'payment_amount' => 100_000, 'payment_status' => 'pending',
        ]);

        $expiredCount = app(ExpirePendingOrdersAction::class)->execute(app(NotificationService::class));
        $this->assertSame(0, $expiredCount);
        $this->assertSame(Order::STATUS_PENDING_PAYMENT, $order->fresh()->status);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame(Product::STATUS_RESERVED, $product->fresh()->status);

        try {
            app(CreateOrderAction::class)->execute($buyer->id, $offer->id, [
                'recipient_name' => 'Người mua thử', 'phone' => '0900000000', 'address' => 'Địa chỉ kiểm thử',
            ]);
            $this->fail('A second order must not release a product tied to an unresolved VietQR reference.');
        } catch (ConflictHttpException) {
            $this->assertSame(1, Order::where('product_id', $product->id)->count());
            $this->assertSame(Order::STATUS_PENDING_PAYMENT, $order->fresh()->status);
            $this->assertSame('pending', $order->fresh()->payment_status);
            $this->assertSame(Product::STATUS_RESERVED, $product->fresh()->status);
        }
    }
}
