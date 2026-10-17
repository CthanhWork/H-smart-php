<?php

namespace Tests\Feature;

use App\Modules\Auth\Services\TokenService;
use App\Modules\Offer\Models\Offer;
use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReviewAndMessageTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email): User
    {
        return User::create(['username' => $email, 'email' => $email, 'password_hash' => Hash::make('violet-forest-river-2026'), 'role' => 'member', 'status' => 'active']);
    }

    private function token(User $user): string { return app(TokenService::class)->accessToken($user); }

    private function completedOrder(User $buyer, User $seller): Order
    {
        $product = Product::create(['seller_id' => $seller->id, 'title' => 'Sách cũ', 'price' => 50000, 'condition' => 'good', 'status' => 'sold']);
        $offer = Offer::create(['product_id' => $product->id, 'buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'offer_price' => 50000, 'status' => 'ordered', 'expires_at' => now()->addDay()]);
        return Order::create(['product_id' => $product->id, 'buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'offer_id' => $offer->id,
            'product_amount' => 50000, 'shipping_fee' => 0, 'platform_fee' => 0, 'total_amount' => 50000, 'status' => 'completed',
            'shipping_address_snapshot' => ['recipient_name' => 'Người mua', 'phone' => '0900000000', 'address' => 'Địa chỉ thử'],
            'expires_at' => now()->subDay(), 'completed_at' => now()]);
    }

    public function test_only_buyer_can_review_once_after_completed_order(): void
    {
        $buyer = $this->user('buyer@example.com'); $seller = $this->user('seller@example.com'); $other = $this->user('other@example.com');
        $order = $this->completedOrder($buyer, $seller);
        $this->withToken($this->token($other))->postJson("/api/v1/orders/{$order->id}/review", ['rating' => 5])->assertNotFound();
        $response = $this->withToken($this->token($buyer))->postJson("/api/v1/orders/{$order->id}/review", ['rating' => 5, 'comment' => 'Đúng mô tả'])
            ->assertCreated()->assertJsonPath('data.rating', 5);
        $this->withToken($this->token($buyer))->postJson("/api/v1/orders/{$order->id}/review", ['rating' => 4])->assertUnprocessable();
        $this->withToken($this->token($buyer))->getJson("/api/v1/products/{$order->product_id}/reviews")
            ->assertOk()->assertJsonPath('data.average', 5)->assertJsonPath('data.content.0.comment', 'Đúng mô tả');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'buyer_id' => $buyer->id, 'review_rating' => 5]);
    }

    public function test_review_is_rejected_until_order_is_completed(): void
    {
        $buyer = $this->user('buyer@example.com'); $seller = $this->user('seller@example.com');
        $order = $this->completedOrder($buyer, $seller);
        $order->update(['status' => 'shipping', 'completed_at' => null]);
        $this->withToken($this->token($buyer))->postJson("/api/v1/orders/{$order->id}/review", ['rating' => 4])->assertStatus(409);
        $this->assertDatabaseMissing('orders', ['id' => $order->id, 'review_rating' => 4]);
    }

    public function test_buyer_and_seller_can_exchange_messages_on_a_product(): void
    {
        $buyer = $this->user('buyer@example.com'); $seller = $this->user('seller@example.com'); $other = $this->user('other@example.com');
        $product = Product::create(['seller_id' => $seller->id, 'title' => 'Sách cũ', 'price' => 50000, 'condition' => 'good', 'status' => 'active']);
        $this->withToken($this->token($buyer))->postJson("/api/v1/products/{$product->id}/messages", ['recipient_id' => $seller->id, 'body' => 'Sách còn tốt không?'])
            ->assertCreated()->assertJsonPath('data.body', 'Sách còn tốt không?');
        $this->withToken($this->token($other))->getJson("/api/v1/messages/{$product->id}/{$buyer->id}")->assertNotFound();
        $this->withToken($this->token($seller))->getJson('/api/v1/messages')->assertOk()->assertJsonPath('data.0.user_id', $buyer->id);
        $this->withToken($this->token($seller))->postJson("/api/v1/products/{$product->id}/messages", ['recipient_id' => $buyer->id, 'body' => 'Sách còn tốt.'])
            ->assertCreated();
        $this->withToken($this->token($buyer))->getJson("/api/v1/messages/{$product->id}/{$seller->id}")
            ->assertOk()->assertJsonCount(2, 'data.messages');
    }
}
