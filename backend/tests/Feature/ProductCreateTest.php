<?php

namespace Tests\Feature;

use App\Modules\Auth\Services\TokenService;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductImage;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductCreateTest extends TestCase
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

    private function payload(): array
    {
        return [
            'title' => 'Máy tính cầm tay',
            'description' => 'Máy còn dùng tốt, đã thay pin.',
            'price' => 150000,
            'condition' => 'good',
        ];
    }

    private function token(User $user): string
    {
        return app(TokenService::class)->accessToken($user);
    }

    public function test_only_authenticated_user_can_create_a_product_owned_by_them(): void
    {
        $this->postJson('/api/v1/products', $this->payload())->assertUnauthorized();
        $this->assertDatabaseCount('products', 0);

        $seller = $this->user('seller@example.com');
        $other = $this->user('other@example.com');
        $this->withToken($this->token($seller))->postJson('/api/v1/products', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.seller_id', $seller->id)
            ->assertJsonPath('data.status', 'pending_review')
            ->assertJsonPath('data.condition', 'good');

        $this->assertDatabaseHas('products', [
            'seller_id' => $seller->id,
            'title' => 'Máy tính cầm tay',
            'price' => 150000,
            'status' => 'pending_review',
        ]);
        $this->assertNotEquals($other->id, Product::firstOrFail()->seller_id);
    }

    public function test_invalid_fields_and_seller_spoofing_are_rejected(): void
    {
        $seller = $this->user('seller@example.com');
        $this->withToken($this->token($seller))->postJson('/api/v1/products', [
            'title' => 'A',
            'price' => 0,
            'condition' => 'broken',
            'seller_id' => 999,
            'status' => 'active',
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'title', 'description', 'price', 'condition', 'seller_id', 'status',
        ]);
        $this->assertDatabaseCount('products', 0);

        $seller->update(['status' => 'suspended']);
        $this->withToken($this->token($seller))->postJson('/api/v1/products', $this->payload())
            ->assertUnauthorized();
        $this->assertDatabaseCount('products', 0);
    }

    public function test_images_are_saved_in_order_and_invalid_upload_is_rejected(): void
    {
        Storage::fake('public');
        $seller = $this->user('seller@example.com');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
        $response = $this->withToken($this->token($seller))->post('/api/v1/products', [
            ...$this->payload(),
            'images' => [
                UploadedFile::fake()->createWithContent('first.png', $png),
                UploadedFile::fake()->createWithContent('second.png', $png),
            ],
        ], ['Accept' => 'application/json']);
        $response->assertCreated()->assertJsonCount(2, 'data.images');
        $this->assertSame([0, 1], ProductImage::orderBy('sort_order')->pluck('sort_order')->all());
        foreach ($response->json('data.images') as $image) {
            $path = (string) parse_url($image['url'], PHP_URL_PATH);
            Storage::disk('public')->assertExists(substr($path, strlen('/storage/')));
        }

        $this->withToken($this->token($seller))->post('/api/v1/products', [
            ...$this->payload(),
            'images' => [UploadedFile::fake()->createWithContent('script.php', '<?php echo 1;')],
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('images.0');
        $this->withToken($this->token($seller))->post('/api/v1/products', [
            ...$this->payload(),
            'images' => array_map(
                fn ($index) => UploadedFile::fake()->createWithContent("image-$index.png", $png),
                range(1, 6),
            ),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('images');
        $this->assertDatabaseCount('products', 1);
    }
}
