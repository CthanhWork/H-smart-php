<?php

namespace App\Modules\Auth\Http\Middleware;

use App\Modules\Auth\Services\TokenService;
use App\Modules\User\Queries\UserIdentityQuery;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateJwt
{
    public function __construct(private TokenService $tokens, private UserIdentityQuery $users) {}

    public function handle(Request $request, Closure $next): Response
    {
        $bearer = (string) $request->bearerToken();
        $identity = $this->tokens->verifyAccessToken($bearer);
        $user = $identity ? $this->users->byId($identity['id']) : null;
        if (! $user || $user->status !== 'active' || (int) $user->auth_version !== $identity['version']) {
            return response()->json(['status' => 'error', 'message' => 'Yêu cầu đăng nhập.', 'data' => null], 401);
        }

        // Set on the default guard so $request->user() resolves correctly
        // in controllers (default guard is `api` in this app).
        $defaultGuard = Auth::getDefaultDriver();
        Auth::guard($defaultGuard)->setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
