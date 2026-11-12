<?php

namespace Tests\Feature;

use App\Modules\Admin\Http\Controllers\AdminCodController;
use App\Modules\Order\Actions\AdvanceOrderAction;
use App\Modules\Order\Actions\CreateOrderAction;
use App\Modules\Order\Models\Order;
use App\Modules\Offer\Models\Offer;
use App\Modules\Product\Models\Product;
use App\Modules\User\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CodSettlementTest extends TestCase
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
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'session.driver' => 'array',
            'cache.default' => 'array',
        ]);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    }

    private function codOrder(): array
    {
        $buyer = User::create(['username' => 'cod-buyer', 'email' => 'cod-buyer@example.test', 'password_hash' => 'unused']);
        $seller = User::create(['username' => 'cod-seller', 'email' => 'cod-seller@example.test', 'password_hash' => 'unused']);
        $product = Product::create(['seller_id' => $seller->id, 'title' => 'COD product', 'price' => 200000, 'condition' => 'good', 'status' => 'reserved']);
        $order = Order::create([
            'buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'product_id' => $product->id,
            'product_amount' => 200000, 'shipping_fee' => 25000, 'platform_fee' => 10000,
            'commission_rate' => 5, 'total_amount' => 225000, 'status' => Order::STATUS_SHIPPING,
            'shipping_address_snapshot' => [], 'expires_at' => now()->addMinutes(15),
            'payment_status' => 'pending', 'payment_amount' => 225000, 'payment_provider' => 'cod',
            'delivery_status' => 'in_transit',
        ]);

        return [$order, $product, $seller];
    }

    private function adminAction(int $orderId, string $action, ?string $reason = null): void
    {
        $request = Request::create('/api/v1/admin/orders/'.$orderId.'/cod', 'POST', array_filter([
            'action' => $action,
            'reason' => $reason,
        ], fn ($value) => $value !== null));
        app(AdminCodController::class)->update($request, $orderId);
    }

    public function test_cod_collection_settlement_and_seller_payout_are_single_use(): void
    {
        [$order] = $this->codOrder();
        $product = Product::findOrFail($order->product_id);
        $this->adminAction($order->id, 'delivered');
        $this->assertSame(Order::STATUS_COMPLETED, $order->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertNotNull($order->fresh()->cod_collected_at);
        $this->assertSame('sold', $product->fresh()->status);
        try {
            $this->adminAction($order->id, 'delivered');
            $this->fail('COD collection should be rejected after it has been recorded.');
        } catch (ConflictHttpException) {
        }

        $this->adminAction($order->id, 'carrier_settled');
        $this->adminAction($order->id, 'seller_paid');
        $this->assertNotNull($order->fresh()->carrier_settled_at);
        $this->assertNotNull($order->fresh()->seller_paid_at);
        try {
            $this->adminAction($order->id, 'seller_paid');
            $this->fail('Seller payout should be rejected after it has been recorded.');
        } catch (ConflictHttpException) {
        }
    }

    public function test_order_creation_snapshots_commission_and_shipping_for_cod(): void
    {
        config(['services.marketplace.commission_rate' => 5, 'services.marketplace.shipping_fee' => 25000]);
        $buyer = User::create(['username' => 'quote-buyer', 'email' => 'quote-buyer@example.test', 'password_hash' => 'unused']);
        $seller = User::create(['username' => 'quote-seller', 'email' => 'quote-seller@example.test', 'password_hash' => 'unused']);
        $product = Product::create(['seller_id' => $seller->id, 'title' => 'Quoted product', 'price' => 250000, 'condition' => 'good', 'status' => 'active']);
        $offer = Offer::create([
            'product_id' => $product->id, 'buyer_id' => $buyer->id, 'seller_id' => $seller->id,
            'offer_price' => 200000, 'status' => Offer::STATUS_ACCEPTED, 'expires_at' => now()->addHour(),
        ]);

        $order = app(CreateOrderAction::class)->execute($buyer->id, $offer->id, [
            'recipient_name' => 'COD Buyer', 'phone' => '0900000000', 'address' => 'Test address',
        ], 'cod');

        $this->assertSame(10000, $order->platform_fee);
        $this->assertSame(5, $order->commission_rate);
        $this->assertSame(25000, $order->shipping_fee);
        $this->assertSame(225000, $order->total_amount);
        $this->assertSame('cod', $order->payment_provider);
        $this->assertSame('awaiting_shipment', $order->delivery_status);
        $confirmed = app(AdvanceOrderAction::class)->execute($order->id, $seller->id, 'confirm');
        $this->assertSame(Order::STATUS_PROCESSING, $confirmed->status);
        $shipped = app(AdvanceOrderAction::class)->execute($order->id, $seller->id, 'ship', [
            'shipping_carrier' => 'Demo carrier', 'tracking_number' => 'COD-001',
        ]);
        $this->assertSame(Order::STATUS_SHIPPING, $shipped->status);
        $this->assertSame('in_transit', $shipped->delivery_status);
    }

    public function test_failed_delivery_can_retry_or_return_and_requires_seller_confirmation_to_release_product(): void
    {
        [$order, $product, $seller] = $this->codOrder();
        $this->adminAction($order->id, 'delivery_failed', 'Không liên hệ được người nhận');
        $this->adminAction($order->id, 'retry_delivery');
        $this->assertSame('in_transit', $order->fresh()->delivery_status);

        $this->adminAction($order->id, 'delivery_failed', 'Người nhận từ chối nhận');
        $this->adminAction($order->id, 'returned');
        $this->assertSame('reserved', $product->fresh()->status);
        app(AdvanceOrderAction::class)->execute($order->id, $seller->id, 'return_received');
        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertSame('active', $product->fresh()->status);
    }
}
