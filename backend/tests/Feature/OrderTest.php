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

class OrderTest extends TestCase
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

    private function headers(User $user): array
    {
        return ['Authorization' => 'Bearer '.app(TokenService::class)->accessToken($user)];
    }

    private function acceptedOffer(User $buyer, User $seller): Offer
    {
        $product = Product::create([
            'seller_id' => $seller->id,
            'title' => 'Máy tính cầm tay',
            'description' => 'Sản phẩm còn dùng tốt.',
            'price' => 200000,
            'condition' => 'good',
            'negotiable' => true,
            'status' => Product::STATUS_ACTIVE,
        ]);

        return Offer::create([
            'product_id' => $product->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'offer_price' => 180000,
            'status' => Offer::STATUS_ACCEPTED,
            'expires_at' => now()->addDay(),
        ]);
    }

    private function address(): array
    {
        return ['recipient_name' => 'Nguyễn An', 'phone' => '0901234567', 'address' => '12 Nguyễn Huệ, Quận 1'];
    }

    public function test_accepted_offer_creates_pending_order_and_reserves_product(): void
    {
        $buyer = $this->user('buyer@example.com');
        $seller = $this->user('seller@example.com');
        $offer = $this->acceptedOffer($buyer, $seller);

        $response = $this->withHeaders($this->headers($buyer))->postJson('/api/v1/orders', [
            'offer_id' => $offer->id, ...$this->address(),
        ])->assertCreated()->assertJsonPath('data.status', 'pending_payment')
            ->assertJsonPath('data.total_amount', 180000)
            ->assertJsonPath('data.payment.provider', 'mock')
            ->assertJsonPath('data.payment.status', 'pending');

        $orderId = $response->json('data.id');
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'buyer_id' => $buyer->id, 'seller_id' => $seller->id]);
        $this->assertDatabaseHas('products', ['id' => $offer->product_id, 'status' => Product::STATUS_RESERVED]);
        $this->assertDatabaseHas('offers', ['id' => $offer->id, 'status' => Offer::STATUS_ORDERED]);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'payment_status' => 'pending', 'payment_provider' => 'mock']);
    }

    public function test_buyer_can_reopen_the_order_created_from_an_offer(): void
    {
        $buyer = $this->user('buyer@example.com');
        $seller = $this->user('seller@example.com');
        $offer = $this->acceptedOffer($buyer, $seller);
        $orderId = $this->withHeaders($this->headers($buyer))->postJson('/api/v1/orders', [
            'offer_id' => $offer->id, ...$this->address(),
        ])->assertCreated()->json('data.id');

        $this->withHeaders($this->headers($buyer))->getJson('/api/v1/orders?offer_id='.$offer->id)
            ->assertOk()
            ->assertJsonPath('data.content.0.id', $orderId)
            ->assertJsonPath('data.content.0.offer_id', $offer->id);

        $this->withHeaders($this->headers($seller))->getJson('/api/v1/orders?offer_id='.$offer->id)
            ->assertOk()
            ->assertJsonPath('data.content.0.id', $orderId);

        $this->withHeaders($this->headers($this->user('other@example.com')))->getJson('/api/v1/orders?offer_id='.$offer->id)
            ->assertOk()
            ->assertJsonPath('data.content', []);
    }

    public function test_only_offer_buyer_can_create_order_and_product_cannot_be_reserved_twice(): void
    {
        $buyer = $this->user('buyer@example.com');
        $seller = $this->user('seller@example.com');
        $other = $this->user('other@example.com');
        $offer = $this->acceptedOffer($buyer, $seller);

        $this->withHeaders($this->headers($other))->postJson('/api/v1/orders', [
            'offer_id' => $offer->id, ...$this->address(),
        ])->assertNotFound();
        $this->assertDatabaseCount('orders', 0);

        $this->withHeaders($this->headers($buyer))->postJson('/api/v1/orders', [
            'offer_id' => $offer->id, ...$this->address(),
        ])->assertCreated();
        $this->withHeaders($this->headers($buyer))->postJson('/api/v1/orders', [
            'offer_id' => $offer->id, ...$this->address(),
        ])->assertStatus(409);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_mock_payment_and_order_state_transitions_enforce_roles(): void
    {
        $buyer = $this->user('buyer@example.com');
        $seller = $this->user('seller@example.com');
        $other = $this->user('other@example.com');
        $offer = $this->acceptedOffer($buyer, $seller);
        $orderId = $this->withHeaders($this->headers($buyer))->postJson('/api/v1/orders', [
            'offer_id' => $offer->id, ...$this->address(),
        ])->assertCreated()->json('data.id');

        $this->withHeaders($this->headers($other))->postJson("/api/v1/orders/{$orderId}/simulate-payment")->assertNotFound();
        $this->withHeaders($this->headers($buyer))->postJson("/api/v1/orders/{$orderId}/simulate-payment")
            ->assertOk()->assertJsonPath('data.status', 'paid')->assertJsonPath('data.payment.status', 'paid');
        $this->assertDatabaseHas('products', ['id' => $offer->product_id, 'status' => Product::STATUS_SOLD]);
        $this->withHeaders($this->headers($buyer))->postJson("/api/v1/orders/{$orderId}/confirm")->assertNotFound();
        $this->withHeaders($this->headers($seller))->postJson("/api/v1/orders/{$orderId}/confirm")
            ->assertOk()->assertJsonPath('data.status', 'processing');
        $this->withHeaders($this->headers($seller))->postJson("/api/v1/orders/{$orderId}/ship", [
            'shipping_carrier' => 'GHN', 'tracking_number' => 'GHN123456',
        ])->assertOk()->assertJsonPath('data.status', 'shipping')
            ->assertJsonPath('data.shipping_carrier', 'GHN')
            ->assertJsonPath('data.tracking_number', 'GHN123456');
        $this->withHeaders($this->headers($seller))->postJson("/api/v1/orders/{$orderId}/complete")->assertNotFound();
        $this->withHeaders($this->headers($buyer))->postJson("/api/v1/orders/{$orderId}/complete")
            ->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertDatabaseHas('offers', ['id' => $offer->id, 'status' => Offer::STATUS_ORDERED]);
    }

    public function test_buyer_can_cancel_unpaid_order_and_release_product_for_another_checkout(): void
    {
        $buyer = $this->user('buyer@example.com');
        $seller = $this->user('seller@example.com');
        $offer = $this->acceptedOffer($buyer, $seller);
        $orderId = $this->withHeaders($this->headers($buyer))->postJson('/api/v1/orders', [
            'offer_id' => $offer->id, ...$this->address(),
        ])->assertCreated()->json('data.id');

        $this->withHeaders($this->headers($buyer))->postJson("/api/v1/orders/{$orderId}/cancel")
            ->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertDatabaseHas('products', ['id' => $offer->product_id, 'status' => Product::STATUS_ACTIVE]);
        $this->assertDatabaseHas('offers', ['id' => $offer->id, 'status' => Offer::STATUS_ACCEPTED]);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'payment_status' => 'failed']);
        $this->withHeaders($this->headers($buyer))->postJson('/api/v1/orders', [
            'offer_id' => $offer->id, ...$this->address(),
        ])->assertCreated();
    }

    public function test_checkout_requires_shipping_details_and_order_access_is_private(): void
    {
        $buyer = $this->user('buyer@example.com');
        $seller = $this->user('seller@example.com');
        $other = $this->user('other@example.com');
        $offer = $this->acceptedOffer($buyer, $seller);

        $this->withHeaders($this->headers($buyer))->postJson('/api/v1/orders', ['offer_id' => $offer->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['recipient_name', 'phone', 'address']);
        $orderId = $this->withHeaders($this->headers($buyer))->postJson('/api/v1/orders', [
            'offer_id' => $offer->id, ...$this->address(),
        ])->assertCreated()->json('data.id');
        $this->withHeaders($this->headers($other))->getJson("/api/v1/orders/{$orderId}")->assertNotFound();
        $this->withHeaders($this->headers($buyer))->getJson('/api/v1/orders')->assertOk()->assertJsonPath('data.totalElements', 1);
    }

    public function test_expired_checkout_is_cancelled_and_releases_product(): void
    {
        $buyer = $this->user('buyer@example.com');
        $seller = $this->user('seller@example.com');
        $offer = $this->acceptedOffer($buyer, $seller);
        $orderId = $this->withHeaders($this->headers($buyer))->postJson('/api/v1/orders', [
            'offer_id' => $offer->id, ...$this->address(),
        ])->assertCreated()->json('data.id');
        Order::whereKey($orderId)->update(['expires_at' => now()->subMinute()]);

        $this->withHeaders($this->headers($buyer))->postJson("/api/v1/orders/{$orderId}/simulate-payment")
            ->assertStatus(409)->assertJsonPath('data.status', 'cancelled');
        $this->assertDatabaseHas('products', ['id' => $offer->product_id, 'status' => Product::STATUS_ACTIVE]);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'payment_status' => 'expired']);
        $this->assertDatabaseHas('offers', ['id' => $offer->id, 'status' => Offer::STATUS_ACCEPTED]);
        $this->assertDatabaseHas('notifications', ['recipient_id' => $buyer->id, 'type' => 'order_expired']);
        $this->assertDatabaseHas('notifications', ['recipient_id' => $seller->id, 'type' => 'order_expired']);
    }

    public function test_scheduled_expiry_releases_abandoned_checkout(): void
    {
        $buyer = $this->user('buyer@example.com');
        $seller = $this->user('seller@example.com');
        $offer = $this->acceptedOffer($buyer, $seller);
        $orderId = $this->withHeaders($this->headers($buyer))->postJson('/api/v1/orders', [
            'offer_id' => $offer->id, ...$this->address(),
        ])->assertCreated()->json('data.id');
        Order::whereKey($orderId)->update(['expires_at' => now()->subMinute()]);

        $this->artisan('orders:expire-pending')
            ->expectsOutput('Đã giải phóng 1 đơn chờ thanh toán hết hạn.')
            ->assertSuccessful();

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => Order::STATUS_CANCELLED]);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'payment_status' => 'expired']);
        $this->assertDatabaseHas('products', ['id' => $offer->product_id, 'status' => Product::STATUS_ACTIVE]);
        $this->assertDatabaseHas('offers', ['id' => $offer->id, 'status' => Offer::STATUS_ACCEPTED]);
        $this->assertDatabaseHas('notifications', ['recipient_id' => $buyer->id, 'type' => 'order_expired']);
        $this->assertDatabaseHas('notifications', ['recipient_id' => $seller->id, 'type' => 'order_expired']);
    }
}
