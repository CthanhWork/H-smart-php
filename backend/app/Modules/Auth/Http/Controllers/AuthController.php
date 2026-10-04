<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Http\Requests\ChangePasswordRequest;
use App\Modules\Auth\Http\Requests\LoginRequest;
use App\Modules\Auth\Http\Requests\RegisterRequest;
use App\Modules\Auth\Http\Requests\ResetPasswordRequest;
use App\Modules\Auth\Mail\ResetPasswordMail;
use App\Modules\Auth\Mail\VerifyEmailMail;
use App\Modules\Auth\Models\AccountToken;
use App\Modules\Auth\Rules\SafePassword;
use App\Modules\Auth\Services\TokenService;
use App\Modules\User\Actions\ActivateUserAction;
use App\Modules\User\Actions\CreateUserAction;
use App\Modules\User\Actions\ReplacePasswordAction;
use App\Modules\User\Models\User;
use App\Modules\User\Queries\UserIdentityQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function __construct(private TokenService $tokens, private UserIdentityQuery $users) {}

    private function response(string $message, mixed $data = null, int $code = 200): JsonResponse
    {
        return response()->json(['status' => $code < 400 ? 'success' : 'error', 'message' => $message, 'data' => $data], $code);
    }

    private function userData(User $user): array
    {
        return $user->only(['id', 'email', 'username', 'full_name', 'role', 'status']);
    }

    private function sendVerification(User $user): void
    {
        $raw = $this->tokens->opaqueToken((int) $user->id, 'email_verification', 24 * 60);
        $url = rtrim((string) config('auth_tokens.frontend_url'), '/').'/verify-email?token='.$raw;
        DB::afterCommit(fn () => Mail::to($user->email)->send(new VerifyEmailMail($url)));
    }

    public function register(RegisterRequest $request, CreateUserAction $createUser): JsonResponse
    {
        $validated = $request->validated();
        DB::transaction(function () use ($validated, $createUser): void {
            $user = $createUser->execute($validated['email'], $validated['password'], $validated['fullName'] ?? null);
            $this->sendVerification($user);
        });

        return $this->response('Đã đăng ký. Kiểm tra email để xác thực tài khoản.', null, 201);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), ['email' => ['required', 'string', 'email:rfc', 'max:150']])->validate();
        $email = mb_strtolower(trim($data['email']));
        $user = $this->users->byEmail($email);
        if ($user && $user->status === 'pending_verification') {
            DB::transaction(function () use ($user): void {
                AccountToken::where('user_id', $user->id)->where('type', 'email_verification')->whereNull('used_at')->update(['used_at' => now()]);
                $this->sendVerification($user);
            });
        }

        return $this->response('Nếu tài khoản đang chờ xác thực, email mới đã được gửi.');
    }

    public function verifyEmail(Request $request, ActivateUserAction $activate): JsonResponse
    {
        $raw = $request->query('token');
        if (! is_string($raw) || ! preg_match('/^[A-Za-z0-9_-]{43}$/', $raw)) {
            return $this->response('Liên kết xác thực không hợp lệ hoặc đã hết hạn.', null, 422);
        }

        $verified = DB::transaction(function () use ($raw, $activate): bool {
            $token = AccountToken::where('token_hash', hash('sha256', $raw))
                ->where('type', 'email_verification')->lockForUpdate()->first();
            if (! $token || $token->used_at || $token->expires_at->isPast()) {
                return false;
            }

            if (! $activate->execute((int) $token->user_id)) {
                return false;
            }
            $token->update(['used_at' => now()]);

            return true;
        });

        return $verified
            ? $this->response('Email đã được xác thực. Bạn có thể đăng nhập.')
            : $this->response('Liên kết xác thực không hợp lệ hoặc đã hết hạn.', null, 422);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $this->users->byEmail($validated['email']);
        if (! $user || ! password_verify($validated['password'], $user->password_hash)) {
            return $this->response('Email hoặc mật khẩu không đúng.', null, 401);
        }
        if ($user->status !== 'active') {
            return $this->response('Tài khoản chưa xác thực hoặc đã bị khóa.', null, 403);
        }

        if (Hash::needsRehash($user->password_hash)) {
            $user->password_hash = Hash::make($validated['password']);
            $user->save();
        }

        return $this->response('Đăng nhập thành công.', [
            'accessToken' => $this->tokens->accessToken($user),
            'refreshToken' => $this->tokens->opaqueToken((int) $user->id, 'refresh', 30 * 24 * 60),
            'tokenType' => 'Bearer',
            'user' => $this->userData($user),
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), ['refreshToken' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{43}$/']])->validate();
        $result = DB::transaction(function () use ($data): ?array {
            $token = AccountToken::where('token_hash', hash('sha256', $data['refreshToken']))
                ->where('type', 'refresh')->lockForUpdate()->first();
            if (! $token) {
                return null;
            }
            if ($token->used_at) {
                AccountToken::where('user_id', $token->user_id)->where('type', 'refresh')->whereNull('used_at')->update(['used_at' => now()]);

                return null;
            }
            if ($token->expires_at->isPast()) {
                return null;
            }
            $user = $this->users->byId((int) $token->user_id);
            if (! $user || $user->status !== 'active') {
                return null;
            }
            $token->update(['used_at' => now()]);

            return [
                'accessToken' => $this->tokens->accessToken($user),
                'refreshToken' => $this->tokens->opaqueToken((int) $user->id, 'refresh', 30 * 24 * 60),
                'tokenType' => 'Bearer',
                'user' => $this->userData($user),
            ];
        });

        return $result ? $this->response('Đã làm mới phiên đăng nhập.', $result) : $this->response('Refresh token không hợp lệ.', null, 401);
    }

    public function logout(Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), ['refreshToken' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{43}$/']])->validate();
        AccountToken::where('token_hash', hash('sha256', $data['refreshToken']))
            ->where('type', 'refresh')->where('user_id', $request->user()->id)
            ->whereNull('used_at')->update(['used_at' => now()]);

        return $this->response('Đã đăng xuất.');
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), ['email' => ['required', 'string', 'email:rfc', 'max:150']])->validate();
        $user = $this->users->byEmail(mb_strtolower(trim($data['email'])));
        if ($user && $user->status === 'active') {
            DB::transaction(function () use ($user): void {
                AccountToken::where('user_id', $user->id)->where('type', 'password_reset')
                    ->whereNull('used_at')->update(['used_at' => now()]);
                $raw = $this->tokens->opaqueToken((int) $user->id, 'password_reset', 60);
                $url = rtrim((string) config('auth_tokens.frontend_url'), '/').'/reset-password?token='.$raw;
                DB::afterCommit(fn () => Mail::to($user->email)->send(new ResetPasswordMail($url)));
            });
        }

        return $this->response('Nếu email thuộc tài khoản hợp lệ, liên kết đặt lại mật khẩu đã được gửi.');
    }

    public function resetPassword(ResetPasswordRequest $request, ReplacePasswordAction $replace): JsonResponse
    {
        $data = $request->validated();
        $reset = DB::transaction(function () use ($data, $replace): bool {
            $token = AccountToken::where('token_hash', hash('sha256', $data['token']))
                ->where('type', 'password_reset')->lockForUpdate()->first();
            if (! $token || $token->used_at || $token->expires_at->isPast()) {
                return false;
            }
            $user = $this->users->byId((int) $token->user_id);
            if (! $user || $user->status !== 'active') {
                return false;
            }
            Validator::make(['password' => $data['password']], [
                'password' => [new SafePassword($user->email)],
            ])->validate();

            $replace->afterReset((int) $user->id, $data['password']);
            AccountToken::where('user_id', $user->id)->whereIn('type', ['password_reset', 'refresh'])
                ->whereNull('used_at')->update(['used_at' => now()]);

            return true;
        });

        return $reset
            ? $this->response('Mật khẩu đã được đặt lại. Hãy đăng nhập bằng mật khẩu mới.')
            : $this->response('Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.', null, 422);
    }

    public function changePassword(ChangePasswordRequest $request, ReplacePasswordAction $replace): JsonResponse
    {
        $data = $request->validated();
        $userId = (int) $request->user()->id;
        DB::transaction(function () use ($data, $userId, $replace): void {
            $replace->withCurrentPassword($userId, $data['currentPassword'], $data['password']);
            AccountToken::where('user_id', $userId)->where('type', 'refresh')
                ->whereNull('used_at')->update(['used_at' => now()]);
        });

        return $this->response('Mật khẩu đã đổi. Hãy đăng nhập lại.');
    }

    public function logoutAll(Request $request, ReplacePasswordAction $replace): JsonResponse
    {
        $userId = (int) $request->user()->id;
        DB::transaction(function () use ($userId, $replace): void {
            $replace->revokeSessions($userId);
            AccountToken::where('user_id', $userId)->where('type', 'refresh')
                ->whereNull('used_at')->update(['used_at' => now()]);
        });

        return $this->response('Đã đăng xuất khỏi tất cả thiết bị.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->response('Tài khoản hiện tại.', ['user' => $this->userData($request->user())]);
    }
}
