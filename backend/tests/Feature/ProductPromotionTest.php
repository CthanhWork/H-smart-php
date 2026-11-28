<?php

namespace Tests\Feature;

use App\Modules\Product\Models\Category;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductPromotion;
use App\Modules\Product\Services\ProductPromotionService;
use App\Modules\Product\Services\ProductSearch;
use App\Modules\User\Models\User;
use App\Modules\Wallet\Services\WalletLedger;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ProductPromotionTest extends TestCase
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

    public function test_vip_and_boost_rank_only_within_matching_search_and_category_scope(): void
    {
        $seller = User::create(['username' => 'seller', 'email' => 'seller@example.test', 'password_hash' => 'unused']);
        $category = Category::create(['name' => 'Thiết bị', 'slug' => 'thiet-bi']);
        $otherCategory = Category::create(['name' => 'Sách', 'slug' => 'sach']);
        $old = now()->subDays(2);
        $normalAt = $old->copy()->addDay();
        $normal = Product::create(['seller_id' => $seller->id, 'category_id' => $category->id, 'title' => 'Máy lọc nước thường', 'price' => 100000, 'condition' => 'good', 'status' => 'active', 'created_at' => $normalAt, 'updated_at' => $normalAt]);
        $boost = Product::create(['seller_id' => $seller->id, 'category_id' => $category->id, 'title' => 'Máy lọc nước đẩy', 'price' => 100000, 'condition' => 'good', 'status' => 'active', 'created_at' => $old, 'updated_at' => $old]);
        $vip = Product::create(['seller_id' => $seller->id, 'category_id' => $category->id, 'title' => 'Máy lọc nước VIP', 'price' => 100000, 'condition' => 'good', 'status' => 'active', 'created_at' => $old, 'updated_at' => $old]);
        $unrelated = Product::create(['seller_id' => $seller->id, 'category_id' => $otherCategory->id, 'title' => 'Máy lọc nước sách', 'price' => 100000, 'condition' => 'good', 'status' => 'active']);
        $sold = Product::create(['seller_id' => $seller->id, 'category_id' => $category->id, 'title' => 'Máy lọc nước đã bán', 'price' => 100000, 'condition' => 'good', 'status' => 'sold']);
        DB::table('products')->where('id', $normal->id)->update(['created_at' => $normalAt, 'updated_at' => $normalAt]);
        DB::table('products')->whereIn('id', [$boost->id, $vip->id])->update(['created_at' => $old, 'updated_at' => $old]);
        foreach ([[$boost, 'boost'], [$vip, 'vip'], [$sold, 'vip']] as [$product, $type]) {
            ProductPromotion::create(['product_id' => $product->id, 'seller_id' => $seller->id, 'package_type' => $type, 'fee_amount' => $type === 'vip' ? 15000 : 10000, 'status' => 'active', 'reference' => 'PROMO_TEST_'.$product->id, 'idempotency_key' => 'promo-test-key-'.$product->id, 'starts_at' => now()->subDay(), 'expires_at' => now()->addDay()]);
        }

        $categoryResults = app(ProductSearch::class)->query(['category_id' => $category->id], 'máy lọc nước')->pluck('id')->all();
        $homeResults = app(ProductSearch::class)->query([], 'máy lọc nước')->pluck('id')->all();
        $this->assertSame([$vip->id, $boost->id, $normal->id], $categoryResults);
        $this->assertSame($vip->id, $homeResults[0]);
        $this->assertLessThan(array_search($boost->id, $homeResults, true), array_search($normal->id, $homeResults, true));
        $this->assertNotContains($unrelated->id, $categoryResults);
        $this->assertNotContains($sold->id, $categoryResults);
    }

    public function test_expiry_job_marks_promotions_expired_and_removes_priority(): void
    {
        $seller = User::create(['username' => 'seller', 'email' => 'seller@example.test', 'password_hash' => 'unused']);
        $product = Product::create(['seller_id' => $seller->id, 'title' => 'Máy giặt VIP', 'price' => 100000, 'condition' => 'good', 'status' => 'active']);
        $promotion = ProductPromotion::create(['product_id' => $product->id, 'seller_id' => $seller->id, 'package_type' => 'vip', 'fee_amount' => 15000, 'status' => 'active', 'reference' => 'PROMO_EXPIRE_TEST', 'idempotency_key' => 'promo-expire-test-key', 'starts_at' => now()->subDays(8), 'expires_at' => now()->subMinute()]);

        $this->assertSame(1, app(ProductPromotionService::class)->expire());
        $this->assertSame('expired', $promotion->refresh()->status);
        $this->assertNull($product->refresh()->load('currentPromotion')->currentPromotion);
    }

    public function test_duplicate_promotion_requests_only_debit_the_wallet_once(): void
    {
        $seller = User::create(['username' => 'seller', 'email' => 'seller@example.test', 'password_hash' => 'unused']);
        $product = Product::create(['seller_id' => $seller->id, 'title' => 'Điện thoại', 'price' => 100000, 'condition' => 'good', 'status' => 'active']);
        $ledger = app(WalletLedger::class);
        $ledger->post($seller->id, 'PROMO_TEST_DEPOSIT', 'simulated_deposit', [
            ['user_id' => $seller->id, 'account_code' => 'available', 'amount' => 15000],
            ['user_id' => null, 'account_code' => 'system_simulation_clearing', 'amount' => -15000],
        ], 'test', null, ['amount' => 15000]);

        $service = app(ProductPromotionService::class);
        $first = $service->purchase($seller->id, $product->id, 'vip', 15000, 'promotion-retry-key-0001', $ledger);
        $retry = $service->purchase($seller->id, $product->id, 'vip', 15000, 'promotion-retry-key-0001', $ledger);

        $this->assertSame($first['promotion']->id, $retry['promotion']->id);
        $this->assertSame(0, $ledger->balance($seller->id)['available']);
        $this->assertSame(1, ProductPromotion::where('idempotency_key', 'promotion-retry-key-0001')->count());

        try {
            $service->purchase($seller->id, $product->id, 'vip', 14_000, 'promotion-retry-key-0001', $ledger);
            $this->fail('Changed promotion fee reused an existing key');
        } catch (ConflictHttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
    }
}
