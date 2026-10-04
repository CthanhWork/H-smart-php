<?php

namespace App\Modules\Auth\Http\Middleware;

use App\Modules\Auth\Services\TokenService;
use App\Modules\User\Queries\UserIdentityQuery;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateJwt
{
    public function __construct(private TokenService $tokens, private UserIdentityQuery $users) {}

    public function handle(Request $request, Closure $next): Response
    {
        $identity = $this->tokens->verifyAccessToken((string) $request->bearerToken());
        $user = $identity ? $this->users->byId($identity['id']) : null;
        if (! $user || $user->status !== 'active' || (int) $user->auth_version !== $identity['version']) {
            return response()->json(['status' => 'error', 'message' => 'Yêu cầu đăng nhập.', 'data' => null], 401);
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
