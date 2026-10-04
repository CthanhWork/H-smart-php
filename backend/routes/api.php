<?php

use App\Modules\Auth\Http\Controllers\AuthController;
use App\Modules\Auth\Http\Middleware\AuthenticateJwt;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth-register');
    Route::post('resend-verification', [AuthController::class, 'resendVerification'])->middleware('throttle:auth-register');
    Route::get('verify-email', [AuthController::class, 'verifyEmail'])->name('auth.verify-email')->middleware('throttle:auth-verify');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth-login');
    Route::post('refresh', [AuthController::class, 'refresh'])->middleware('throttle:auth-refresh');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:auth-recovery');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth-recovery');
    Route::middleware(AuthenticateJwt::class)->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('logout-all', [AuthController::class, 'logoutAll']);
        Route::post('change-password', [AuthController::class, 'changePassword'])->middleware('throttle:auth-recovery');
        Route::get('me', [AuthController::class, 'me']);
    });
});
