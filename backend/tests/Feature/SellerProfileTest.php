<?php

namespace Tests\Feature;

use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use App\Modules\User\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;

class SellerProfileTest extends TestCase
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

    public function test_public_seller_profile_exposes_only_trust_summary(): void
    {
        $seller = User::create([
            'username' => 'qa-seller',
            'email' => 'seller-private@example.test',
            'password_hash' => 'unused',
            'full_name' => 'QA Seller',
            'status' => 'active',
        ]);
        $buyer = User::create(['username' => 'qa-buyer', 'email' => 'buyer@example.test', 'password_hash' => 'unused', 'status' => 'active']);
        $product = Product::create([
            'seller_id' => $seller->id,
            'title' => 'QA product',
            'price' => 1000,
            'condition' => 'good',
            'status' => 'active',
        ]);
        Order::create([
            'product_id' => $product->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'product_amount' => 1000,
            'total_amount' => 1000,
            'status' => Order::STATUS_COMPLETED,
            'shipping_address_snapshot' => [],
            'expires_at' => now()->addHour(),
            'review_rating' => 5,
        ]);

        $this->getJson("/api/v1/sellers/{$seller->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $seller->id)
            ->assertJsonPath('data.name', 'QA Seller')
            ->assertJsonPath('data.completed_sales_count', 1)
            ->assertJsonPath('data.review_average', 5)
            ->assertJsonPath('data.review_count', 1)
            ->assertJsonPath('data.active_products_count', 1)
            ->assertJsonMissingPath('data.email');
    }

    public function test_suspended_seller_profile_is_not_public(): void
    {
        $seller = User::create([
            'username' => 'suspended-seller',
            'email' => 'suspended@example.test',
            'password_hash' => 'unused',
            'status' => 'suspended',
        ]);

        $this->getJson("/api/v1/sellers/{$seller->id}")->assertNotFound();
    }
}
