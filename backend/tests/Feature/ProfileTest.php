<?php

namespace Tests\Feature;

use App\Modules\Auth\Services\TokenService;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create([
            'username' => 'profile@example.test',
            'email' => 'profile@example.test',
            'password_hash' => Hash::make('violet-forest-river-2026'),
            'role' => 'member',
            'status' => 'active',
        ]);
    }

    public function test_member_can_view_and_update_their_profile_and_default_shipping_address(): void
    {
        $user = $this->user();
        $headers = ['Authorization' => 'Bearer '.app(TokenService::class)->accessToken($user)];

        $this->withHeaders($headers)->getJson('/api/v1/me/profile')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.shipping_address', null);

        $this->withHeaders($headers)->putJson('/api/v1/me/profile', [
            'full_name' => 'Nguyễn Minh Anh',
            'phone_number' => '0912345678',
            'shipping_address' => '12 Nguyễn Trãi, Quận 5, TP. Hồ Chí Minh',
        ])->assertOk()
            ->assertJsonPath('data.full_name', 'Nguyễn Minh Anh')
            ->assertJsonPath('data.phone_number', '0912345678')
            ->assertJsonPath('data.shipping_address', '12 Nguyễn Trãi, Quận 5, TP. Hồ Chí Minh');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => $user->email,
            'shipping_address' => '12 Nguyễn Trãi, Quận 5, TP. Hồ Chí Minh',
        ]);
    }

    public function test_profile_requires_authentication_and_valid_contact_data(): void
    {
        $this->getJson('/api/v1/me/profile')->assertUnauthorized();

        $user = $this->user();
        $headers = ['Authorization' => 'Bearer '.app(TokenService::class)->accessToken($user)];

        $this->withHeaders($headers)->putJson('/api/v1/me/profile', [
            'full_name' => 'A',
            'phone_number' => '123',
            'shipping_address' => 'x',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['phone_number', 'shipping_address']);
    }
}
