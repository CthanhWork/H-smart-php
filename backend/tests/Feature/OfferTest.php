<?php

namespace Tests\Feature;

use App\Modules\Auth\Services\TokenService;
use App\Modules\Offer\Models\Offer;
use App\Modules\Product\Models\Product;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfferTest extends TestCase
{
    use RefreshDatabase;

    private function bearer(User $user): array
    {
        $token = app(TokenService::class)->accessToken($user);

        return ['Authorization' => 'Bearer '.$token];
    }

    private function createNegotiableProduct(int $sellerId, int $price = 10000000): Product
    {
        return Product::create([
            'seller_id' => $sellerId,
            'title' => 'iPhone 12 Pro',
            'description' => 'Test product',
            'price' => $price,
            'condition' => 'like_new',
            'negotiable' => true,
            'status' => Product::STATUS_ACTIVE,
        ]);
    }

    public function test_authenticated_buyer_can_create_offer_on_negotiable_product(): void
    {
        $seller = User::factory()->create(['status' => 'active']);
        $buyer = User::factory()->create(['status' => 'active']);
        $product = $this->createNegotiableProduct($seller->id);

        $headers = $this->bearer($buyer);
        $response = $this->withHeaders($headers)->postJson('/api/v1/offers', [
            'product_id' => $product->id,
            'offer_price' => 9000000,
        ]);

        $response->assertStatus(201)
            ->assertJson(['status' => 'success'])
            ->assertJsonStructure(['data' => ['id', 'offer_price', 'status', 'expires_at']]);

        $this->assertDatabaseHas('offers', [
            'product_id' => $product->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'offer_price' => 9000000,
            'status' => 'pending',
        ]);
    }

    public function test_buyer_cannot_offer_on_own_product(): void
    {
        $seller = User::factory()->create(['status' => 'active']);
        $product = $this->createNegotiableProduct($seller->id);

        $response = $this->withHeaders($this->bearer($seller))->postJson('/api/v1/offers', [
            'product_id' => $product->id,
            'offer_price' => 9000000,
        ]);

        $response->assertStatus(422)
            ->assertJson(['status' => 'error']);
    }

    public function test_buyer_cannot_create_duplicate_pending_offer(): void
    {
        $seller = User::factory()->create(['status' => 'active']);
        $buyer = User::factory()->create(['status' => 'active']);
        $product = $this->createNegotiableProduct($seller->id);

        // Create first offer
        Offer::create([
            'product_id' => $product->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'offer_price' => 9000000,
            'status' => 'pending',
            'expires_at' => now()->addDay(),
        ]);

        // Try to create second offer
        $response = $this->withHeaders($this->bearer($buyer))->postJson('/api/v1/offers', [
            'product_id' => $product->id,
            'offer_price' => 8500000,
        ]);

        $response->assertStatus(422);
    }

    public function test_cannot_offer_on_non_negotiable_product(): void
    {
        $seller = User::factory()->create(['status' => 'active']);
        $buyer = User::factory()->create(['status' => 'active']);

        $product = Product::create([
            'seller_id' => $seller->id,
            'title' => 'iPhone Fixed Price',
            'description' => 'Test product',
            'price' => 10000000,
            'condition' => 'like_new',
            'negotiable' => false,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $response = $this->withHeaders($this->bearer($buyer))->postJson('/api/v1/offers', [
            'product_id' => $product->id,
            'offer_price' => 9000000,
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['status' => 'error']);
    }

    public function test_offer_price_must_respect_product_price_range(): void
    {
        $seller = User::factory()->create(['status' => 'active']);
        $buyer = User::factory()->create(['status' => 'active']);
        $product = $this->createNegotiableProduct($seller->id, 150000);
        $product->update(['min_price' => 100000]);

        $this->withHeaders($this->bearer($buyer))->postJson('/api/v1/offers', [
            'product_id' => $product->id, 'offer_price' => 90000,
        ])->assertUnprocessable()->assertJsonPath('message', 'Giá đề nghị thấp hơn mức tối thiểu người bán chấp nhận.');

        $this->withHeaders($this->bearer($buyer))->postJson('/api/v1/offers', [
            'product_id' => $product->id, 'offer_price' => 160000,
        ])->assertUnprocessable()->assertJsonPath('message', 'Giá đề nghị không được cao hơn giá bán.');

        $this->assertDatabaseCount('offers', 0);
    }

    public function test_offer_price_must_be_positive(): void
    {
        $seller = User::factory()->create(['status' => 'active']);
        $buyer = User::factory()->create(['status' => 'active']);
        $product = $this->createNegotiableProduct($seller->id);

        $response = $this->withHeaders($this->bearer($buyer))->postJson('/api/v1/offers', [
            'product_id' => $product->id,
            'offer_price' => 0,
        ]);

        $response->assertStatus(422);
    }

    public function test_seller_can_accept_pending_offer(): void
    {
        $seller = User::factory()->create(['status' => 'active']);
        $buyer = User::factory()->create(['status' => 'active']);
        $product = $this->createNegotiableProduct($seller->id);

        $offer = Offer::create([
            'product_id' => $product->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'offer_price' => 9000000,
            'status' => 'pending',
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->withHeaders($this->bearer($seller))->postJson("/api/v1/offers/{$offer->id}/accept");

        $response->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('offers', [
            'id' => $offer->id,
            'status' => 'accepted',
        ]);
    }

    public function test_only_one_accepted_offer_per_product(): void
    {
        $seller = User::factory()->create(['status' => 'active']);
        $buyer1 = User::factory()->create(['status' => 'active']);
        $buyer2 = User::factory()->create(['status' => 'active']);
        $product = $this->createNegotiableProduct($seller->id);

        // First offer already accepted
        Offer::create([
            'product_id' => $product->id,
            'buyer_id' => $buyer1->id,
            'seller_id' => $seller->id,
            'offer_price' => 9000000,
            'status' => 'accepted',
            'expires_at' => now()->addDay(),
        ]);

        // Second offer pending — AcceptOfferAction must refuse because
        // another accepted offer already exists for this product.
        $offer2 = Offer::create([
            'product_id' => $product->id,
            'buyer_id' => $buyer2->id,
            'seller_id' => $seller->id,
            'offer_price' => 8500000,
            'status' => 'pending',
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->withHeaders($this->bearer($seller))->postJson("/api/v1/offers/{$offer2->id}/accept");

        $response->assertStatus(422);
    }

    public function test_seller_can_reject_offer(): void
    {
        $seller = User::factory()->create(['status' => 'active']);
        $buyer = User::factory()->create(['status' => 'active']);
        $product = $this->createNegotiableProduct($seller->id);

        $offer = Offer::create([
            'product_id' => $product->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'offer_price' => 9000000,
            'status' => 'pending',
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->withHeaders($this->bearer($seller))->postJson("/api/v1/offers/{$offer->id}/reject");

        $response->assertStatus(200);

        $this->assertDatabaseHas('offers', [
            'id' => $offer->id,
            'status' => 'rejected',
        ]);
    }

    public function test_buyer_can_cancel_pending_offer(): void
    {
        $seller = User::factory()->create(['status' => 'active']);
        $buyer = User::factory()->create(['status' => 'active']);
        $product = $this->createNegotiableProduct($seller->id);

        $offer = Offer::create([
            'product_id' => $product->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'offer_price' => 9000000,
            'status' => 'pending',
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->withHeaders($this->bearer($buyer))->postJson("/api/v1/offers/{$offer->id}/cancel");

        $response->assertStatus(200);

        $this->assertDatabaseHas('offers', [
            'id' => $offer->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_cannot_accept_expired_offer(): void
    {
        $seller = User::factory()->create(['status' => 'active']);
        $buyer = User::factory()->create(['status' => 'active']);
        $product = $this->createNegotiableProduct($seller->id);

        $offer = Offer::create([
            'product_id' => $product->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'offer_price' => 9000000,
            'status' => 'pending',
            'expires_at' => now()->subHour(),
        ]);

        $response = $this->withHeaders($this->bearer($seller))->postJson("/api/v1/offers/{$offer->id}/accept");

        $response->assertStatus(422);
    }

    public function test_buyer_and_seller_can_view_their_offers(): void
    {
        $seller = User::factory()->create(['status' => 'active']);
        $buyer = User::factory()->create(['status' => 'active']);
        $product = $this->createNegotiableProduct($seller->id);

        $offer = Offer::create([
            'product_id' => $product->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'offer_price' => 9000000,
            'status' => 'pending',
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->withHeaders($this->bearer($buyer))->getJson("/api/v1/offers/{$offer->id}");
        $response->assertStatus(200);

        $response = $this->withHeaders($this->bearer($seller))->getJson("/api/v1/offers/{$offer->id}");
        $response->assertStatus(200);
    }

    public function test_cannot_view_others_offers(): void
    {
        $seller = User::factory()->create(['status' => 'active']);
        $buyer = User::factory()->create(['status' => 'active']);
        $other = User::factory()->create(['status' => 'active']);
        $product = $this->createNegotiableProduct($seller->id);

        $offer = Offer::create([
            'product_id' => $product->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'offer_price' => 9000000,
            'status' => 'pending',
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->withHeaders($this->bearer($other))->getJson("/api/v1/offers/{$offer->id}");
        $response->assertStatus(403);
    }

    public function test_list_offers_shows_user_offers_only(): void
    {
        $seller = User::factory()->create(['status' => 'active']);
        $buyer = User::factory()->create(['status' => 'active']);
        $other = User::factory()->create(['status' => 'active']);

        $product = $this->createNegotiableProduct($seller->id);

        // Buyer's offer
        Offer::create([
            'product_id' => $product->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'offer_price' => 9000000,
            'status' => 'pending',
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->withHeaders($this->bearer($buyer))->getJson('/api/v1/offers');
        $response->assertStatus(200)
            ->assertJsonPath('data.totalElements', 1);

        $response = $this->withHeaders($this->bearer($seller))->getJson('/api/v1/offers');
        $response->assertStatus(200)
            ->assertJsonPath('data.totalElements', 1);

        $response = $this->withHeaders($this->bearer($other))->getJson('/api/v1/offers');
        $response->assertStatus(200)
            ->assertJsonPath('data.totalElements', 0);
    }

    public function test_unauthenticated_user_cannot_create_offer(): void
    {
        $seller = User::factory()->create(['status' => 'active']);
        $product = $this->createNegotiableProduct($seller->id);

        $response = $this->postJson('/api/v1/offers', [
            'product_id' => $product->id,
            'offer_price' => 9000000,
        ]);

        $response->assertStatus(401);
    }

    public function test_only_seller_can_accept(): void
    {
        $seller = User::factory()->create(['status' => 'active']);
        $buyer = User::factory()->create(['status' => 'active']);
        $stranger = User::factory()->create(['status' => 'active']);
        $product = $this->createNegotiableProduct($seller->id);

        $offer = Offer::create([
            'product_id' => $product->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'offer_price' => 9000000,
            'status' => 'pending',
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->withHeaders($this->bearer($stranger))->postJson("/api/v1/offers/{$offer->id}/accept");
        $response->assertStatus(403);
    }

    public function test_only_buyer_can_cancel(): void
    {
        $seller = User::factory()->create(['status' => 'active']);
        $buyer = User::factory()->create(['status' => 'active']);
        $stranger = User::factory()->create(['status' => 'active']);
        $product = $this->createNegotiableProduct($seller->id);

        $offer = Offer::create([
            'product_id' => $product->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'offer_price' => 9000000,
            'status' => 'pending',
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->withHeaders($this->bearer($stranger))->postJson("/api/v1/offers/{$offer->id}/cancel");
        $response->assertStatus(403);
    }

    public function test_cannot_offer_on_inactive_product(): void
    {
        $seller = User::factory()->create(['status' => 'active']);
        $buyer = User::factory()->create(['status' => 'active']);
        $product = Product::create([
            'seller_id' => $seller->id,
            'title' => 'iPhone Sold',
            'description' => 'Sold product',
            'price' => 10000000,
            'condition' => 'like_new',
            'negotiable' => true,
            'status' => Product::STATUS_SOLD,
        ]);

        $response = $this->withHeaders($this->bearer($buyer))->postJson('/api/v1/offers', [
            'product_id' => $product->id,
            'offer_price' => 9000000,
        ]);

        $response->assertStatus(422);
    }
}
