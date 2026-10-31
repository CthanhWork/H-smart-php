<?php

use App\Modules\Auth\Http\Middleware\AuthenticateJwt;
use App\Modules\Review\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', AuthenticateJwt::class])->prefix('api/v1')->group(function (): void {
    Route::get('products/{productId}/reviews', [ReviewController::class, 'index'])->whereNumber('productId');
    Route::post('orders/{orderId}/review', [ReviewController::class, 'store'])->whereNumber('orderId');
});
