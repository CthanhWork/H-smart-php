<?php

namespace Tests\Feature;

use App\Modules\Auth\Services\TokenService;
use App\Modules\Product\Models\Category;
use App\Modules\Product\Models\Product;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email, string $role = 'member'): User
    {
        return User::create(['username' => $email, 'email' => $email, 'password_hash' => Hash::make('violet-forest-river-2026'), 'role' => $role, 'status' => 'active']);
    }

    private function token(User $user): string { return app(TokenService::class)->accessToken($user); }

    public function test_admin_can_manage_categories_and_cannot_delete_used_category(): void
    {
        $admin = $this->user('admin@example.com', 'admin');
        $member = $this->user('member@example.com');
        $this->withToken($this->token($member))->postJson('/api/v1/admin/categories', ['name' => 'Nhạc cụ'])->assertForbidden();
        $created = $this->withToken($this->token($admin))->postJson('/api/v1/admin/categories', ['name' => 'Nhạc cụ'])
            ->assertCreated()->assertJsonPath('data.slug', 'nhac-cu')->json('data');
        $this->withToken($this->token($admin))->patchJson('/api/v1/admin/categories/'.$created['id'], ['name' => 'Nhạc cụ và âm thanh'])
            ->assertOk()->assertJsonPath('data.name', 'Nhạc cụ và âm thanh');
        $seller = $this->user('seller@example.com');
        $product = Product::create(['seller_id' => $seller->id, 'category_id' => $created['id'], 'title' => 'Đàn guitar', 'price' => 10000, 'condition' => 'good', 'status' => 'active']);
        $this->withToken($this->token($admin))->deleteJson('/api/v1/admin/categories/'.$created['id'])->assertStatus(409);
        $product->delete();
        $this->withToken($this->token($admin))->deleteJson('/api/v1/admin/categories/'.$created['id'])->assertOk();
    }

    public function test_category_filter_only_returns_matching_products(): void
    {
        $seller = $this->user('seller@example.com');
        $buyer = $this->user('buyer@example.com');
        $categories = Category::query()->orderBy('id')->take(2)->get();
        Product::create(['seller_id' => $seller->id, 'category_id' => $categories[0]->id, 'title' => 'Sách toán', 'price' => 10000, 'condition' => 'good', 'status' => 'active']);
        Product::create(['seller_id' => $seller->id, 'category_id' => $categories[1]->id, 'title' => 'Bàn học', 'price' => 10000, 'condition' => 'good', 'status' => 'active']);
        $this->withToken($this->token($buyer))->getJson('/api/v1/products?filter[category_id]='.$categories[0]->id)
            ->assertOk()->assertJsonPath('data.totalElements', 1)->assertJsonPath('data.content.0.category.id', $categories[0]->id);
    }

    public function test_guests_can_browse_categories_and_active_products(): void
    {
        $seller = $this->user('seller@example.com');
        $category = Category::query()->firstOrFail();
        $active = Product::create(['seller_id' => $seller->id, 'category_id' => $category->id, 'title' => 'Sách đang bán', 'price' => 10000, 'condition' => 'good', 'status' => 'active']);
        $pending = Product::create(['seller_id' => $seller->id, 'category_id' => $category->id, 'title' => 'Sách chờ duyệt', 'price' => 12000, 'condition' => 'good', 'status' => 'pending_review']);

        $this->getJson('/api/v1/categories')->assertOk()->assertJsonFragment(['id' => $category->id, 'name' => $category->name]);
        $this->getJson('/api/v1/products')->assertOk()->assertJsonPath('data.totalElements', 1)->assertJsonPath('data.content.0.id', $active->id);
        $this->getJson('/api/v1/products/'.$active->id)->assertOk()->assertJsonPath('data.id', $active->id);
        $this->getJson('/api/v1/products/'.$pending->id)->assertNotFound();
    }
}
