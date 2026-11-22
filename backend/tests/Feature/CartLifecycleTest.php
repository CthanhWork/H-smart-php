<?php

namespace Tests\Feature;

use App\Modules\Product\Http\Controllers\CartController;
use App\Modules\Product\Models\CartItem;
use App\Modules\Product\Models\Product;
use App\Modules\User\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CartLifecycleTest extends TestCase
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
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    }

    public function test_cart_add_is_idempotent_and_rejects_own_or_unavailable_products(): void
    {
        $buyer = User::create(['username' => 'buyer', 'email' => 'buyer@example.test', 'password_hash' => 'unused']);
        $seller = User::create(['username' => 'seller', 'email' => 'seller@example.test', 'password_hash' => 'unused']);
        $active = Product::create(['seller_id' => $seller->id, 'title' => 'Bàn học', 'price' => 150_000, 'condition' => 'good', 'status' => Product::STATUS_ACTIVE]);
        $hidden = Product::create(['seller_id' => $seller->id, 'title' => 'Máy ảnh ẩn', 'price' => 200_000, 'condition' => 'good', 'status' => Product::STATUS_HIDDEN]);
        $controller = app(CartController::class);

        $first = $controller->store($this->request($buyer, $active->id));
        $second = $controller->store($this->request($buyer, $active->id));

        $this->assertSame(201, $first->getStatusCode());
        $this->assertSame(200, $second->getStatusCode());
        $this->assertSame(1, CartItem::where('user_id', $buyer->id)->where('product_id', $active->id)->count());

        foreach ([[$seller, $active], [$buyer, $hidden]] as [$user, $product]) {
            try {
                $controller->store($this->request($user, $product->id));
                $this->fail('Cart accepted an own or unavailable product');
            } catch (ConflictHttpException $exception) {
                $this->assertSame(409, $exception->getStatusCode());
            }
        }
    }

    private function request(User $user, int $productId): Request
    {
        $request = Request::create('/api/v1/me/cart', 'POST', ['product_id' => $productId]);
        $request->setUserResolver(fn () => $user);

        return $request;
    }
}
