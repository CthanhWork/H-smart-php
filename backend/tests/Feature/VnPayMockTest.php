<?php

namespace Tests\Feature;

use App\Modules\Order\Models\Order;
use App\Modules\Order\Services\VnPayService;
use App\Modules\Product\Models\Product;
use App\Modules\User\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpKernel\Exception\HttpException;

class VnPayMockTest extends TestCase
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
            'app.key' => 'base64:'.base64_encode(str_repeat('t', 32)),
            'app.url' => 'http://localhost:8001',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'session.driver' => 'array',
            'cache.default' => 'array',
            'services.vnpay.mode' => 'mock',
            'services.vnpay.tmn_code' => null,
            'services.vnpay.hash_secret' => null,
            'services.vnpay.result_redirect_url' => 'http://localhost:5174/payment-result',
        ]);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    }

    private function checkout(): array
    {
        $buyer = User::create(['username' => 'buyer', 'email' => 'buyer@example.test', 'password_hash' => 'unused']);
        $seller = User::create(['username' => 'seller', 'email' => 'seller@example.test', 'password_hash' => 'unused']);
        $product = Product::create(['seller_id' => $seller->id, 'title' => 'Test product', 'price' => 150000, 'condition' => 'good', 'status' => 'reserved']);
        $order = Order::create([
            'buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'product_id' => $product->id,
            'product_amount' => 150000, 'total_amount' => 150000, 'status' => 'pending_payment',
            'shipping_address_snapshot' => [], 'expires_at' => now()->addMinutes(15),
            'payment_status' => 'pending', 'payment_amount' => 150000, 'payment_provider' => 'vnpay',
        ]);
        $request = Request::create('http://localhost:5174/api/v1/orders/1/payment');
        $request->setUserResolver(fn () => $buyer);
        $url = app(VnPayService::class)->createPaymentUrl($order, $request);

        return [$order->refresh(), $product, $request, $url];
    }

    public function test_checkout_without_credentials_and_success_are_idempotent(): void
    {
        [$order, $product, , $url] = $this->checkout();
        $this->assertStringStartsWith('http://localhost:8001/payments/vnpay/mock/', $url);
        $this->get($url)->assertOk()->assertSee('Không kết nối VNPay')->assertSee('150.000');
        $this->post($url, ['outcome' => 'success'])->assertRedirect('http://localhost:5174/payment-result?payment=mock_success');
        $paidAt = $order->refresh()->payment_paid_at;
        $this->assertSame('paid', $order->status);
        $this->assertSame('vnpay_mock', $order->payment_provider);
        $this->assertSame('sold', $product->refresh()->status);
        $this->post($url, ['outcome' => 'success'])->assertRedirect();
        $this->assertTrue($paidAt->equalTo($order->refresh()->payment_paid_at));
        $this->assertSame(1, $order->statusHistory()->where('to_status', 'paid')->count());
        $this->assertSame(1, DB::table('notifications')->where('type', 'order_paid')->count());
        $this->post($url, ['outcome' => 'cancel'])->assertRedirect();
        $this->assertSame('paid', $order->refresh()->status);
    }

    public function test_failure_cancel_and_retry_invalidate_the_old_attempt(): void
    {
        [$order, $product, $request, $url] = $this->checkout();
        $this->post($url, ['outcome' => 'failed'])->assertRedirect('http://localhost:5174/payment-result?payment=mock_failed');
        $this->assertSame('failed', $order->refresh()->payment_status);
        $this->assertSame('reserved', $product->refresh()->status);
        $newUrl = app(VnPayService::class)->createPaymentUrl($order, $request);
        $this->assertNotSame($url, $newUrl);
        $this->post($url, ['outcome' => 'success'])->assertNotFound();
        $this->post($newUrl, ['outcome' => 'cancel'])->assertRedirect();
        $this->assertSame('24', $order->refresh()->payment_response_code);
        $this->assertSame('pending_payment', $order->status);
        $retry = app(VnPayService::class)->createPaymentUrl($order, $request);
        $this->post($retry, ['outcome' => 'success'])->assertRedirect();
        $this->assertSame('paid', $order->refresh()->status);
    }

    public function test_signed_url_tampering_expiry_and_production_are_rejected(): void
    {
        [$order, , , $url] = $this->checkout();
        $this->post($url.'&amount=1', ['outcome' => 'success'])->assertForbidden();
        $this->post($url, ['outcome' => 'unknown'])->assertSessionHasErrors('outcome');
        $this->travel(16)->minutes();
        $this->post($url, ['outcome' => 'success'])->assertForbidden();
        $this->travelBack();
        $this->app->instance('env', 'production');
        $this->get($url)->assertNotFound();
        $this->post($url, ['outcome' => 'success'])->assertStatus(419);
        $this->assertSame('pending', $order->refresh()->payment_status);
        try {
            app(VnPayService::class)->createPaymentUrl($order, Request::create('/'));
            $this->fail('Production allowed mock checkout');
        } catch (HttpException $e) {
            $this->assertSame(503, $e->getStatusCode());
        }
    }

    public function test_expired_order_and_wrong_buyer_cannot_be_paid(): void
    {
        [$order, , $request] = $this->checkout();
        $request->setUserResolver(fn () => User::find($order->seller_id));
        try {
            app(VnPayService::class)->createPaymentUrl($order, $request);
            $this->fail('Wrong buyer was accepted');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
        $order->update(['expires_at' => now()->subMinute()]);
        $url = URL::temporarySignedRoute('payments.vnpay.mock', now()->addMinute(), ['txnRef' => $order->payment_txn_ref], absolute: false);
        $this->post($url, ['outcome' => 'success'])->assertRedirect('http://localhost:5174/payment-result?payment=invalid');
        $this->assertSame('pending', $order->refresh()->payment_status);
    }

    public function test_gateway_callbacks_cannot_pay_mock_orders_and_gateway_still_signs_requests(): void
    {
        [$order, , $request] = $this->checkout();
        config(['services.vnpay.tmn_code' => 'TEST', 'services.vnpay.hash_secret' => 'test-secret']);
        $query = ['vnp_TmnCode' => 'TEST', 'vnp_TxnRef' => $order->payment_txn_ref, 'vnp_Amount' => '15000000', 'vnp_ResponseCode' => '00', 'vnp_TransactionStatus' => '00'];
        ksort($query);
        $query['vnp_SecureHash'] = hash_hmac('sha512', http_build_query($query), 'test-secret');
        $this->assertSame('invalid', app(VnPayService::class)->processIpn($query));
        config(['services.vnpay.mode' => 'gateway']);
        $url = app(VnPayService::class)->createPaymentUrl($order, $request);
        parse_str(parse_url($url, PHP_URL_QUERY), $params);
        $this->assertTrue(app(VnPayService::class)->verify($params));
        $this->assertSame('vnpay', $order->refresh()->payment_provider);
        $this->assertNotSame($query['vnp_TxnRef'], $order->payment_txn_ref);
    }

    public function test_failed_payment_releases_the_product_after_order_expires(): void
    {
        [$order, $product, , $url] = $this->checkout();
        $this->post($url, ['outcome' => 'failed'])->assertRedirect();
        $this->travel(16)->minutes();
        $count = app(\App\Modules\Order\Actions\ExpirePendingOrdersAction::class)
            ->execute(app(\App\Modules\Notification\Services\NotificationService::class));
        $this->assertSame(1, $count);
        $this->assertSame('cancelled', $order->refresh()->status);
        $this->assertSame('active', $product->refresh()->status);
        $this->travelBack();
    }
}
