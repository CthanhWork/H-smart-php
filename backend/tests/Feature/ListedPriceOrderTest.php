<?php

namespace Tests\Feature;

use App\Modules\Order\Actions\CreateListedOrderAction;
use App\Modules\Order\Http\Controllers\OrderController;
use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Services\ProductSearch;
use App\Modules\User\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ListedPriceOrderTest extends TestCase
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
        config(['services.marketplace.shipping_fee' => 25000, 'services.marketplace.commission_rate' => 2]);
    }

    public function test_listed_price_checkout_snapshots_fee_and_retries_idempotently(): void
    {
        $buyer = User::create(['username' => 'buyer', 'email' => 'buyer@example.test', 'password_hash' => 'unused']);
        $seller = User::create(['username' => 'seller', 'email' => 'seller@example.test', 'password_hash' => 'unused']);
        $product = Product::create(['seller_id' => $seller->id, 'title' => 'Bàn học', 'price' => 1_000_000, 'condition' => 'good', 'status' => 'active']);

        $action = app(CreateListedOrderAction::class);
        $order = $action->execute($buyer->id, $product->id, 1_000_000, 'checkout-test-key-0001', ['recipient_name' => 'B', 'phone' => '0900000000', 'address' => 'Đường thử nghiệm'], 'vnpay');
        $retry = $action->execute($buyer->id, $product->id, 1_000_000, 'checkout-test-key-0001', ['recipient_name' => 'B', 'phone' => '0900000000', 'address' => 'Đường thử nghiệm'], 'vnpay');

        $this->assertSame($order->id, $retry->id);
        $this->assertSame('listed_price', $order->purchase_type);
        $this->assertSame(1_025_000, $order->fee_base);
        $this->assertSame(20_500, $order->platform_fee);
        $this->assertSame(2, $order->commission_rate);
        $this->assertSame('reserved', $product->refresh()->status);
        $this->assertSame(1, Order::where('idempotency_key', 'checkout-test-key-0001')->count());
    }

    public function test_reusing_idempotency_key_with_changed_payload_is_rejected(): void
    {
        $buyer = User::create(['username' => 'buyer', 'email' => 'buyer@example.test', 'password_hash' => 'unused']);
        $seller = User::create(['username' => 'seller', 'email' => 'seller@example.test', 'password_hash' => 'unused']);
        $product = Product::create(['seller_id' => $seller->id, 'title' => 'Máy ảnh', 'price' => 500_000, 'condition' => 'good', 'status' => 'active']);
        $action = app(CreateListedOrderAction::class);
        $address = ['recipient_name' => 'Buyer', 'phone' => '0900000000', 'address' => 'Address one'];
        $order = $action->execute($buyer->id, $product->id, 500_000, 'checkout-payload-key-0001', $address, 'vnpay');

        try {
            $action->execute($buyer->id, $product->id, 499_000, 'checkout-payload-key-0001', [
                'recipient_name' => 'Buyer', 'phone' => '0900000000', 'address' => 'Address two',
            ], 'cod');
            $this->fail('Changed payload reused an existing idempotency key');
        } catch (ConflictHttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        $this->assertSame(1, Order::where('idempotency_key', 'checkout-payload-key-0001')->count());
        $this->assertSame('vnpay', $order->refresh()->payment_method);
        $this->assertSame($address, $order->shipping_address_snapshot);
    }

    public function test_changed_price_and_self_purchase_are_rejected(): void
    {
        $buyer = User::create(['username' => 'buyer', 'email' => 'buyer@example.test', 'password_hash' => 'unused']);
        $seller = User::create(['username' => 'seller', 'email' => 'seller@example.test', 'password_hash' => 'unused']);
        $product = Product::create(['seller_id' => $seller->id, 'title' => 'Máy tính', 'price' => 500000, 'condition' => 'good', 'status' => 'active']);
        $action = app(CreateListedOrderAction::class);
        try {
            $action->execute($buyer->id, $product->id, 400000, 'checkout-price-key-0001', [], 'vnpay');
            $this->fail('Changed price was accepted');
        } catch (ConflictHttpException $exception) {
            $this->assertStringContainsString('thay đổi', $exception->getMessage());
        }
        try {
            $action->execute($seller->id, $product->id, 500000, 'checkout-self-key-0001', [], 'vnpay');
            $this->fail('Self-purchase was accepted');
        } catch (ConflictHttpException $exception) {
            $this->assertStringContainsString('chính mình', $exception->getMessage());
        }
    }

    public function test_reserved_product_cannot_be_bought_by_a_second_buyer(): void
    {
        $firstBuyer = User::create(['username' => 'buyer-one', 'email' => 'buyer-one@example.test', 'password_hash' => 'unused']);
        $secondBuyer = User::create(['username' => 'buyer-two', 'email' => 'buyer-two@example.test', 'password_hash' => 'unused']);
        $seller = User::create(['username' => 'seller', 'email' => 'seller@example.test', 'password_hash' => 'unused']);
        $product = Product::create(['seller_id' => $seller->id, 'title' => 'Máy ảnh dùng thử', 'price' => 750000, 'condition' => 'good', 'status' => Product::STATUS_ACTIVE]);
        $action = app(CreateListedOrderAction::class);

        $action->execute($firstBuyer->id, $product->id, 750000, 'checkout-buyer-one-0001', [], 'vnpay');

        try {
            $action->execute($secondBuyer->id, $product->id, 750000, 'checkout-buyer-two-0001', [], 'vnpay');
            $this->fail('A second buyer reserved an already-held product.');
        } catch (ConflictHttpException $exception) {
            $this->assertStringContainsString('không còn khả dụng', $exception->getMessage());
            $this->assertSame(1, Order::where('product_id', $product->id)->count());
        }
    }

    public function test_shared_product_search_only_returns_active_products_matching_filters(): void
    {
        $seller = User::create(['username' => 'seller', 'email' => 'seller@example.test', 'password_hash' => 'unused']);
        $active = Product::create(['seller_id' => $seller->id, 'title' => 'Nồi cơm điện tốt', 'price' => 450000, 'condition' => 'good', 'status' => 'active']);
        Product::create(['seller_id' => $seller->id, 'title' => 'Nồi cơm điện nháp', 'price' => 350000, 'condition' => 'good', 'status' => 'pending_review']);
        Product::create(['seller_id' => $seller->id, 'title' => 'Nồi cơm điện đắt', 'price' => 850000, 'condition' => 'good', 'status' => 'active']);

        $results = app(ProductSearch::class)->query(['max_price' => 500000, 'condition' => 'good'], 'nồi cơm điện')->get();
        $this->assertSame([$active->id], $results->pluck('id')->all());
    }

    public function test_product_quote_accepts_one_purchase_source_and_rejects_both(): void
    {
        $buyer = User::create(['username' => 'quote-buyer', 'email' => 'quote-buyer@example.test', 'password_hash' => 'unused']);
        $seller = User::create(['username' => 'quote-seller', 'email' => 'quote-seller@example.test', 'password_hash' => 'unused']);
        $product = Product::create(['seller_id' => $seller->id, 'title' => 'Máy ảnh báo giá', 'price' => 500000, 'condition' => 'good', 'status' => Product::STATUS_ACTIVE]);
        $request = Request::create('/api/v1/orders/quote', 'GET', ['product_id' => $product->id]);
        $request->setUserResolver(fn () => $buyer);

        $quote = app(OrderController::class)->quote($request)->getData(true);
        $this->assertSame('success', $quote['status']);
        $this->assertSame(500000, $quote['data']['product_amount']);

        $ambiguous = Request::create('/api/v1/orders/quote', 'GET', ['product_id' => $product->id, 'offer_id' => 999]);
        $ambiguous->setUserResolver(fn () => $buyer);
        $this->expectException(ValidationException::class);
        app(OrderController::class)->quote($ambiguous);
    }
}
