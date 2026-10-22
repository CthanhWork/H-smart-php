<?php

use App\Modules\Auth\Http\Middleware\AuthenticateJwt;
use App\Modules\Message\Http\Controllers\MessageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', AuthenticateJwt::class])->prefix('api/v1')->group(function (): void {
    Route::get('messages', [MessageController::class, 'index']);
    Route::get('messages/contacts', [MessageController::class, 'contacts']);
    Route::post('messages', [MessageController::class, 'storeDirect']);
    Route::get('messages/{userId}', [MessageController::class, 'showDirect'])->whereNumber('userId');
    Route::get('messages/{productId}/{userId}', [MessageController::class, 'show'])->whereNumber('productId')->whereNumber('userId');
    Route::post('products/{productId}/messages', [MessageController::class, 'store'])->whereNumber('productId');
});
