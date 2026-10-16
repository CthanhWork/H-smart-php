<?php

namespace Tests\Feature;

use App\Modules\Auth\Services\TokenService;
use App\Modules\Product\Models\Favorite;
use App\Modules\Product\Models\Product;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email): User
    {
        return User::create([
            'username' => $email,
            'email' => $email,
            'password_hash' => Hash::make('violet-forest-river-2026'),
            'role' => 'member',
            'status' => 'active',
        ]);
    }

    private function product(User $seller, string $title, int $price, string $condition = 'good', string $status = Product::STATUS_ACTIVE): Product
    {
        return Product::create([
            'seller_id' => $seller->id,
            'title' => $title,
            'description' => 'Tin đăng kiểm thử.',
            'price' => $price,
            'condition' => $condition,
            'negotiable' => false,
            'status' => $status,
        ]);
    }

    private function headers(User $user): array
    {
        return ['Authorization' => 'Bearer '.app(TokenService::class)->accessToken($user)];
    }

    public function test_product_search_filters_by_price_and_condition_then_sorts_by_price(): void
    {
        $seller = $this->user('seller@example.test');
        $this->product($seller, 'Laptop giá thấp', 500000, 'good');
        $this->product($seller, 'Laptop giá cao', 2500000, 'good');
        $this->product($seller, 'Laptop like new', 800000, 'like_new');

        $this->getJson('/api/v1/products?q=Laptop&filter[min_price]=400000&filter[max_price]=3000000&filter[condition]=good&sort=price_asc&page[size]=10')
            ->assertOk()
            ->assertJsonPath('data.totalElements', 2)
            ->assertJsonPath('data.content.0.title', 'Laptop giá thấp')
            ->assertJsonPath('data.content.1.title', 'Laptop giá cao');
    }

    public function test_member_can_save_view_and_remove_favorites_without_duplicates(): void
    {
        $seller = $this->user('seller@example.test');
        $member = $this->user('member@example.test');
        $otherMember = $this->user('other@example.test');
        $product = $this->product($seller, 'Giáo trình cần mua', 90000);
        $headers = $this->headers($member);

        $this->withHeaders($headers)->postJson('/api/v1/me/favorites', ['product_id' => $product->id])->assertCreated();
        $this->withHeaders($headers)->postJson('/api/v1/me/favorites', ['product_id' => $product->id])->assertCreated();
        $this->assertSame(1, Favorite::where('user_id', $member->id)->where('product_id', $product->id)->count());

        $this->withHeaders($headers)->getJson('/api/v1/me/favorites?page[size]=10')
            ->assertOk()
            ->assertJsonPath('data.totalElements', 1)
            ->assertJsonPath('data.content.0.is_favorite', true);
        $this->withHeaders($this->headers($otherMember))->getJson('/api/v1/me/favorites')
            ->assertOk()->assertJsonPath('data.totalElements', 0);

        $this->withHeaders($headers)->deleteJson('/api/v1/me/favorites/'.$product->id)->assertOk();
        $this->assertDatabaseMissing('favorites', ['user_id' => $member->id, 'product_id' => $product->id]);
    }

    public function test_unavailable_products_cannot_be_favorited(): void
    {
        $seller = $this->user('seller@example.test');
        $member = $this->user('member@example.test');
        $hidden = $this->product($seller, 'Tin đã ẩn', 90000, 'good', Product::STATUS_HIDDEN);

        $this->withHeaders($this->headers($member))->postJson('/api/v1/me/favorites', ['product_id' => $hidden->id])
            ->assertUnprocessable();
    }
}
