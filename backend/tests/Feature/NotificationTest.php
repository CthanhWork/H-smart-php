<?php

namespace Tests\Feature;

use App\Modules\Auth\Services\TokenService;
use App\Modules\Product\Models\Product;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function headers(User $user): array
    {
        return ['Authorization' => 'Bearer '.app(TokenService::class)->accessToken($user)];
    }

    public function test_new_message_and_offer_notify_the_other_party_and_allow_read_tracking(): void
    {
        $seller = User::factory()->create(['status' => 'active', 'full_name' => 'Người bán']);
        $buyer = User::factory()->create(['status' => 'active', 'full_name' => 'Người mua']);
        $product = Product::create(['seller_id' => $seller->id, 'title' => 'Sách cũ', 'price' => 100000, 'condition' => 'good', 'negotiable' => true, 'status' => 'active']);

        $this->withHeaders($this->headers($buyer))->postJson("/api/v1/products/{$product->id}/messages", ['recipient_id' => $seller->id, 'body' => 'Sách còn không?'])->assertCreated();
        $this->assertDatabaseHas('notifications', ['recipient_id' => $seller->id, 'actor_id' => $buyer->id, 'type' => 'new_message']);

        $this->withHeaders($this->headers($buyer))->postJson('/api/v1/offers', ['product_id' => $product->id, 'offer_price' => 90000])->assertCreated();
        $this->assertDatabaseHas('notifications', ['recipient_id' => $seller->id, 'actor_id' => $buyer->id, 'type' => 'offer_received']);

        $response = $this->withHeaders($this->headers($seller))->getJson('/api/v1/notifications')->assertOk()
            ->assertJsonPath('data.unread_count', 2)->assertJsonPath('data.items.0.title', 'Có đề nghị giá mới');
        $notificationId = $response->json('data.items.0.id');

        $this->withHeaders($this->headers($buyer))->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('data.total', 0);
        $this->withHeaders($this->headers($buyer))->patchJson("/api/v1/notifications/{$notificationId}/read")->assertNotFound();
        $this->withHeaders($this->headers($seller))->patchJson("/api/v1/notifications/{$notificationId}/read")->assertOk();
        $this->withHeaders($this->headers($seller))->getJson('/api/v1/notifications/unread-count')->assertOk()->assertJsonPath('data.unread_count', 1);
        $this->withHeaders($this->headers($seller))->patchJson('/api/v1/notifications/read-all')->assertOk();
        $this->withHeaders($this->headers($seller))->getJson('/api/v1/notifications/unread-count')->assertOk()->assertJsonPath('data.unread_count', 0);
    }
}
