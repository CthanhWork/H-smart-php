<?php

namespace Tests\Feature;

use App\Modules\Auth\Mail\ResetPasswordMail;
use App\Modules\Auth\Mail\VerifyEmailMail;
use App\Modules\Auth\Models\AccountToken;
use App\Modules\Auth\Services\TokenService;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'violet-forest-river-2026';

    private function register(string $email = 'student@example.com', string $password = self::PASSWORD): void
    {
        $this->postJson('/api/v1/auth/register', [
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $password,
            'fullName' => 'Sinh Vien',
        ])->assertCreated()->assertDontSee('token_hash')->assertDontSee('accessToken');
    }

    private function verificationToken(): string
    {
        $sent = null;
        Mail::assertSent(VerifyEmailMail::class, function (VerifyEmailMail $mail) use (&$sent): bool {
            $sent = $mail;

            return true;
        });
        $url = $sent->verificationUrl;
        $this->assertStringStartsWith('http://localhost:5173/verify-email?', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query['token'];
    }

    public function test_registration_normalizes_email_and_never_exposes_secrets(): void
    {
        Mail::fake();
        $this->register('  STUDENT@EXAMPLE.COM  ');

        $user = User::firstOrFail();
        $this->assertSame('student@example.com', $user->email);
        $this->assertSame($user->email, $user->username);
        $this->assertSame('pending_verification', $user->status);
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password_hash));
        $this->assertStringStartsWith('$argon2id$', $user->password_hash);
        $this->assertSame(1, AccountToken::count());
        $this->assertSame(64, strlen(AccountToken::firstOrFail()->token_hash));
        $this->assertNotSame(hash('sha256', self::PASSWORD), AccountToken::firstOrFail()->token_hash);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_password_and_duplicate_email_validation(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        Mail::fake();
        foreach (['short', 'password', 'passwordpasswordpassword', 'student-very-long-secret'] as $password) {
            $this->postJson('/api/v1/auth/register', [
                'email' => 'student@example.com',
                'password' => $password,
                'password_confirmation' => $password,
            ])->assertUnprocessable()->assertJsonValidationErrors('password');
        }
        $this->postJson('/api/v1/auth/register', [
            'email' => 'student@example.com',
            'password' => self::PASSWORD,
            'password_confirmation' => 'different',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->register();
        $this->postJson('/api/v1/auth/register', [
            'email' => 'STUDENT@example.com',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_eight_character_password_is_accepted_but_seven_is_rejected(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        Mail::fake();
        $this->postJson('/api/v1/auth/register', [
            'email' => 'student@example.com', 'password' => 'Mango7!', 'password_confirmation' => 'Mango7!',
        ])->assertUnprocessable()->assertJsonValidationErrors('password')->assertJsonPath('status', 'error');
        $this->register('student@example.com', 'Mango7!q');
        $this->assertTrue(Hash::check('Mango7!q', User::firstOrFail()->password_hash));
    }

    public function test_email_must_be_verified_before_login_and_link_is_single_use(): void
    {
        Mail::fake();
        $this->register();
        $raw = $this->verificationToken();
        $this->assertSame(hash('sha256', $raw), AccountToken::firstOrFail()->token_hash);

        $this->postJson('/api/v1/auth/login', ['email' => 'student@example.com', 'password' => self::PASSWORD])
            ->assertForbidden();
        $this->getJson('/api/v1/auth/verify-email?token='.$raw)->assertOk();
        $this->assertSame('active', User::firstOrFail()->status);
        $this->getJson('/api/v1/auth/verify-email?token='.$raw)->assertUnprocessable();

        $login = $this->postJson('/api/v1/auth/login', ['email' => 'STUDENT@example.com', 'password' => self::PASSWORD])
            ->assertOk()->assertJsonPath('data.tokenType', 'Bearer');
        $access = $login->json('data.accessToken');
        $refresh = $login->json('data.refreshToken');
        $this->assertNotEmpty($access);
        $this->assertNotEmpty($refresh);
        $this->assertSame(['id' => User::firstOrFail()->id, 'version' => 0], app(TokenService::class)->verifyAccessToken($access));
        $this->withToken($access)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.user.email', 'student@example.com');

        $rotated = $this->postJson('/api/v1/auth/refresh', ['refreshToken' => $refresh])->assertOk()->json('data.refreshToken');
        $this->assertNotSame($refresh, $rotated);
        $this->postJson('/api/v1/auth/refresh', ['refreshToken' => $refresh])->assertUnauthorized();
        $this->postJson('/api/v1/auth/refresh', ['refreshToken' => $rotated])->assertUnauthorized();
    }

    public function test_expired_link_invalid_credentials_and_suspended_account(): void
    {
        Mail::fake();
        $this->register();
        $raw = $this->verificationToken();
        AccountToken::firstOrFail()->update(['expires_at' => now()->subMinute()]);
        $this->getJson('/api/v1/auth/verify-email?token='.$raw)->assertUnprocessable();
        $this->postJson('/api/v1/auth/login', ['email' => 'absent@example.com', 'password' => self::PASSWORD])
            ->assertUnauthorized()->assertJsonPath('message', 'Email hoặc mật khẩu không đúng.');
        $this->postJson('/api/v1/auth/login', ['email' => 'student@example.com', 'password' => 'wrong-secret'])
            ->assertUnauthorized()->assertJsonPath('message', 'Email hoặc mật khẩu không đúng.');
        User::firstOrFail()->update(['status' => 'suspended']);
        $this->postJson('/api/v1/auth/login', ['email' => 'student@example.com', 'password' => self::PASSWORD])
            ->assertForbidden();
    }

    public function test_resend_invalidates_old_link_and_logout_revokes_refresh_token(): void
    {
        Mail::fake();
        $this->register();
        $old = $this->verificationToken();

        $this->postJson('/api/v1/auth/resend-verification', ['email' => 'STUDENT@example.com'])->assertOk();
        Mail::assertSent(VerifyEmailMail::class, 2);
        $this->getJson('/api/v1/auth/verify-email?token='.$old)->assertUnprocessable();

        $all = Mail::sent(VerifyEmailMail::class);
        parse_str((string) parse_url($all->last()->verificationUrl, PHP_URL_QUERY), $query);
        $this->getJson('/api/v1/auth/verify-email?token='.$query['token'])->assertOk();

        $login = $this->postJson('/api/v1/auth/login', ['email' => 'student@example.com', 'password' => self::PASSWORD])->assertOk();
        $this->withToken($login->json('data.accessToken'))
            ->postJson('/api/v1/auth/logout', ['refreshToken' => $login->json('data.refreshToken')])->assertOk();
        $this->postJson('/api/v1/auth/refresh', ['refreshToken' => $login->json('data.refreshToken')])->assertUnauthorized();
    }

    public function test_legacy_bcrypt_password_is_rehashed_after_successful_login(): void
    {
        $user = User::create([
            'username' => 'legacy@example.com', 'email' => 'legacy@example.com',
            'password_hash' => password_hash(self::PASSWORD, PASSWORD_BCRYPT),
            'role' => 'member', 'status' => 'active',
        ]);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertOk();
        $this->assertStringStartsWith('$argon2id$', $user->fresh()->password_hash);
    }

    public function test_password_reset_is_single_use_and_revokes_previous_sessions(): void
    {
        Mail::fake();
        $user = User::create([
            'username' => 'student@example.com', 'email' => 'student@example.com',
            'password_hash' => Hash::make(self::PASSWORD), 'role' => 'member', 'status' => 'active',
        ]);
        $login = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertOk();
        $oldAccess = $login->json('data.accessToken');
        $oldRefresh = $login->json('data.refreshToken');

        $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'unknown@example.com'])->assertOk()->json('message');
        Mail::assertNothingSent();
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'STUDENT@example.com'])->assertOk()->assertJsonPath('message', $unknown);
        Mail::assertSent(ResetPasswordMail::class, 1);
        $mail = Mail::sent(ResetPasswordMail::class)->first();
        parse_str((string) parse_url($mail->resetUrl, PHP_URL_QUERY), $query);
        $raw = $query['token'];
        $this->assertSame(hash('sha256', $raw), AccountToken::where('type', 'password_reset')->firstOrFail()->token_hash);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $raw, 'password' => 'Mango7!', 'password_confirmation' => 'Mango7!',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $raw, 'password' => 'Mango7!q', 'password_confirmation' => 'Mango7!q',
        ])->assertOk();
        $this->assertSame(1, $user->fresh()->auth_version);
        $this->withToken($oldAccess)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->postJson('/api/v1/auth/refresh', ['refreshToken' => $oldRefresh])->assertUnauthorized();
        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $raw, 'password' => 'Violet7!q', 'password_confirmation' => 'Violet7!q',
        ])->assertUnprocessable();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Mango7!q'])->assertOk();
    }

    public function test_change_password_and_logout_all_revoke_access_and_refresh_tokens(): void
    {
        $user = User::create([
            'username' => 'student@example.com', 'email' => 'student@example.com',
            'password_hash' => Hash::make(self::PASSWORD), 'role' => 'member', 'status' => 'active',
        ]);
        $login = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertOk();
        $access = $login->json('data.accessToken');
        $refresh = $login->json('data.refreshToken');
        $this->withToken($access)->postJson('/api/v1/auth/change-password', [
            'currentPassword' => 'incorrect', 'password' => 'Mango7!q', 'password_confirmation' => 'Mango7!q',
        ])->assertUnprocessable()->assertJsonValidationErrors('currentPassword');
        $this->withToken($access)->postJson('/api/v1/auth/change-password', [
            'currentPassword' => self::PASSWORD, 'password' => 'Mango7!q', 'password_confirmation' => 'Mango7!q',
        ])->assertOk();
        $this->withToken($access)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->postJson('/api/v1/auth/refresh', ['refreshToken' => $refresh])->assertUnauthorized();

        $newLogin = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Mango7!q'])->assertOk();
        $newAccess = $newLogin->json('data.accessToken');
        $newRefresh = $newLogin->json('data.refreshToken');
        $this->withToken($newAccess)->postJson('/api/v1/auth/logout-all')->assertOk();
        $this->withToken($newAccess)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->postJson('/api/v1/auth/refresh', ['refreshToken' => $newRefresh])->assertUnauthorized();
    }

    public function test_expired_reset_link_cannot_change_password(): void
    {
        Mail::fake();
        $user = User::create([
            'username' => 'student@example.com', 'email' => 'student@example.com',
            'password_hash' => Hash::make(self::PASSWORD), 'role' => 'member', 'status' => 'active',
        ]);
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();
        parse_str((string) parse_url(Mail::sent(ResetPasswordMail::class)->first()->resetUrl, PHP_URL_QUERY), $query);
        AccountToken::where('type', 'password_reset')->firstOrFail()->update(['expires_at' => now()->subMinute()]);
        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $query['token'], 'password' => 'Mango7!q', 'password_confirmation' => 'Mango7!q',
        ])->assertUnprocessable();
        $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password_hash));
    }

    public function test_login_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'student@example.com', 'password' => 'wrong-secret',
            ])->assertUnauthorized();
        }
        $this->postJson('/api/v1/auth/login', [
            'email' => 'student@example.com', 'password' => 'wrong-secret',
        ])->assertTooManyRequests();
    }
}
