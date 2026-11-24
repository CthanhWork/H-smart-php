<?php

namespace Tests\Feature;

use App\Modules\Order\Http\Resources\OrderResource;
use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use App\Modules\User\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderFinancialVisibilityTest extends TestCase
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

    public function test_financial_fields_follow_the_user_role_on_each_order(): void
    {
        $shared = $this->user('shared');
        $buyerOther = $this->user('buyer');
        $sellerOther = $this->user('seller');
        $admin = User::create(['username' => 'admin', 'email' => 'admin@example.test', 'password_hash' => 'unused', 'role' => 'admin']);
        $asBuyer = $this->order($shared, $sellerOther);
        $asSeller = $this->order($buyerOther, $shared);

        $buyerOrder = $this->serialize($asBuyer, $shared);
        $sellerOrder = $this->serialize($asSeller, $shared);
        $adminBuyerOrder = $this->serialize($asBuyer, $admin);

        $this->assertArrayNotHasKey('seller_financials', $buyerOrder);
        $this->assertArrayNotHasKey('platform_fee', $buyerOrder);
        $this->assertArrayNotHasKey('commission_rate', $buyerOrder);
        $this->assertSame(2_000, $sellerOrder['seller_financials']['platform_fee']);
        $this->assertSame(98_000, $sellerOrder['seller_financials']['seller_net_amount']);
        $this->assertSame(2_000, $adminBuyerOrder['seller_financials']['platform_fee']);
    }

    private function serialize(Order $order, User $viewer): array
    {
        $request = Request::create('/api/v1/orders/'.$order->id);
        $request->setUserResolver(fn () => $viewer);

        return OrderResource::make(Order::with('product')->findOrFail($order->id))->resolve($request);
    }

    private function user(string $name): User
    {
        return User::create(['username' => $name, 'email' => $name.'@example.test', 'password_hash' => 'unused']);
    }

    private function order(User $buyer, User $seller): Order
    {
        $product = Product::create(['seller_id' => $seller->id, 'title' => 'Sản phẩm test', 'price' => 100_000, 'condition' => 'good', 'status' => 'sold']);

        return Order::create([
            'buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'product_id' => $product->id,
            'product_amount' => 100_000, 'shipping_fee' => 0, 'fee_base' => 100_000,
            'platform_fee' => 2_000, 'commission_rate' => 2, 'total_amount' => 100_000,
            'status' => 'completed', 'payment_status' => 'paid', 'payout_status' => 'held',
            'shipping_address_snapshot' => ['recipient_name' => 'Test', 'phone' => '0900000000', 'address' => 'Test'],
            'expires_at' => now()->addDay(), 'completed_at' => now(),
        ]);
    }
}
