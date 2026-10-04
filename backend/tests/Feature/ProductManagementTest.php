<?php

namespace Tests\Feature;

use App\Modules\Auth\Services\TokenService;
use App\Modules\Product\Models\Product;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email): User
    {
        return User::create(['username' => $email, 'email' => $email,
            'password_hash' => Hash::make('violet-forest-river-2026'), 'role' => 'member', 'status' => 'active']);
    }

    private function token(User $user): string
    {
        return app(TokenService::class)->accessToken($user);
    }

    private function product(User $seller, string $title, string $status = 'active', string $condition = 'good', int $price = 100000): Product
    {
        return Product::create(['seller_id' => $seller->id, 'title' => $title,
            'description' => 'Sản phẩm còn dùng tốt.', 'price' => $price, 'condition' => $condition, 'status' => $status]);
    }

    private function payload(): array
    {
        return ['title' => 'Máy tính cầm tay mới', 'description' => 'Mô tả đã cập nhật và đủ dài.', 'price' => 180000, 'condition' => 'like_new'];
    }

    public function test_buyer_only_sees_active_products_with_search_filters_and_pagination(): void
    {
        $seller = $this->user('seller@example.com');
        $buyer = $this->user('buyer@example.com');
        $this->getJson('/api/v1/products')->assertUnauthorized();
        $visible = $this->product($seller, 'Máy tính Casio', 'active', 'good', 150000);
        $this->product($seller, 'Máy tính đắt', 'active', 'new', 500000);
        $pending = $this->product($seller, 'Máy tính chờ', 'pending_review');
        $sold = $this->product($seller, 'Máy tính bán', 'sold');
        $this->withToken($this->token($buyer))->getJson('/api/v1/products?q=Máy&filter[condition]=good&filter[max_price]=200000&page[size]=1')
            ->assertOk()->assertJsonPath('data.totalElements', 1)->assertJsonPath('data.content.0.id', $visible->id);
        $this->withToken($this->token($buyer))->getJson('/api/v1/products?page[size]=1&page[number]=1')
            ->assertOk()->assertJsonPath('data.totalElements', 2)->assertJsonPath('data.pageNo', 1);
        $this->withToken($this->token($buyer))->getJson("/api/v1/products/{$pending->id}")->assertNotFound();
        $this->withToken($this->token($buyer))->getJson("/api/v1/products/{$sold->id}")->assertNotFound();
        $this->withToken($this->token($buyer))->getJson("/api/v1/products/{$visible->id}")->assertOk();
    }

    public function test_owner_can_view_edit_and_delete_only_their_products(): void
    {
        $seller = $this->user('seller@example.com');
        $other = $this->user('other@example.com');
        $product = $this->product($seller, 'Máy tính', 'active');
        $this->withToken($this->token($seller))->getJson('/api/v1/me/products?filter[status]=active')
            ->assertOk()->assertJsonPath('data.content.0.id', $product->id);
        $this->withToken($this->token($other))->getJson("/api/v1/products/{$product->id}")->assertOk();
        $this->withToken($this->token($other))->patchJson("/api/v1/products/{$product->id}", $this->payload())->assertNotFound();
        $this->withToken($this->token($other))->deleteJson("/api/v1/products/{$product->id}")->assertNotFound();
        $this->withToken($this->token($seller))->patchJson("/api/v1/products/{$product->id}", $this->payload())
            ->assertOk()->assertJsonPath('data.status', 'pending_review');
        $this->withToken($this->token($other))->getJson("/api/v1/products/{$product->id}")->assertNotFound();
        $this->withToken($this->token($seller))->getJson("/api/v1/products/{$product->id}")->assertOk();
        $this->withToken($this->token($seller))->deleteJson("/api/v1/products/{$product->id}")->assertOk();
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_edit_manages_images_and_rejects_foreign_images_or_sold_products(): void
    {
        Storage::fake('public');
        $seller = $this->user('seller@example.com');
        $product = $this->product($seller, 'Máy tính');
        $other = $this->product($seller, 'Sản phẩm khác');
        $first = $product->images()->create(['url' => '/storage/products/first.png', 'sort_order' => 0]);
        $second = $product->images()->create(['url' => '/storage/products/second.png', 'sort_order' => 1]);
        $foreign = $other->images()->create(['url' => '/storage/products/foreign.png', 'sort_order' => 0]);
        $this->withToken($this->token($seller))->patchJson("/api/v1/products/{$product->id}", [
            ...$this->payload(), 'remove_image_ids' => [$foreign->id],
        ])->assertUnprocessable();
        $this->withToken($this->token($seller))->patchJson("/api/v1/products/{$product->id}", [
            ...$this->payload(), 'remove_image_ids' => [$first->id], 'image_order' => [$second->id],
        ])->assertOk()->assertJsonCount(1, 'data.images')->assertJsonPath('data.images.0.id', $second->id);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
        $this->withToken($this->token($seller))->post("/api/v1/products/{$product->id}", [
            ...$this->payload(), '_method' => 'PATCH',
            'images' => [UploadedFile::fake()->createWithContent('new.png', $png)],
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonCount(2, 'data.images');
        $product->update(['status' => 'sold']);
        $this->withToken($this->token($seller))->patchJson("/api/v1/products/{$product->id}", $this->payload())->assertStatus(409);
        $this->withToken($this->token($seller))->deleteJson("/api/v1/products/{$product->id}")->assertStatus(409);
    }

    public function test_edit_rejects_seller_spoofing_and_too_many_combined_images(): void
    {
        Storage::fake('public');
        $seller = $this->user('seller@example.com');
        $product = $this->product($seller, 'Máy tính');
        foreach (range(0, 4) as $index) {
            $product->images()->create(['url' => "/storage/products/$index.png", 'sort_order' => $index]);
        }
        $this->withToken($this->token($seller))->patchJson("/api/v1/products/{$product->id}", [
            ...$this->payload(), 'seller_id' => 999, 'status' => 'active',
        ])->assertUnprocessable()->assertJsonValidationErrors(['seller_id', 'status']);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
        $this->withToken($this->token($seller))->post("/api/v1/products/{$product->id}", [
            ...$this->payload(), '_method' => 'PATCH',
            'images' => [UploadedFile::fake()->createWithContent('new.png', $png)],
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('images');
        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => 'active']);
    }
}
