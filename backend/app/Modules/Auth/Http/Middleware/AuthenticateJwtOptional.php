<?php

namespace App\Modules\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateJwtOptional
{
    public function __construct(private AuthenticateJwt $authenticate) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->bearerToken()) {
            return $next($request);
        }

        return $this->authenticate->handle($request, $next);
    }
}
