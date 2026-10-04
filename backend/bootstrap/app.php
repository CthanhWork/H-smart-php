<?php

use App\Modules\Auth\Http\Middleware\AuthenticateJwt;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['auth.jwt' => AuthenticateJwt::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Dữ liệu không hợp lệ.',
                'data' => null,
                'errors' => $exception->errors(),
            ], 422);
        });
        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->is('api/*') || ! in_array($exception->getStatusCode(), [404, 409], true)) {
                return null;
            }

            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage() ?: ($exception->getStatusCode() === 404 ? 'Không tìm thấy dữ liệu.' : 'Trạng thái không hợp lệ.'),
                'data' => null,
            ], $exception->getStatusCode());
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
