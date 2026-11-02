<?php

use App\Modules\Auth\Http\Middleware\AuthenticateJwt;
use App\Modules\Order\Http\Controllers\OrderController;
use App\Modules\Order\Http\Controllers\VnPayController;
use Illuminate\Support\Facades\Route;

Route::middleware('api')->get('/api/v1/payments/vnpay/ipn', [VnPayController::class, 'ipn'])->name('payments.vnpay.ipn');
Route::middleware('api')->get('/api/v1/payments/vnpay/return', [VnPayController::class, 'return'])->name('payments.vnpay.return');

Route::middleware(['api', AuthenticateJwt::class])->prefix('api/v1/orders')->group(function (): void {
    Route::get('/', [OrderController::class, 'index']);
    Route::post('/', [OrderController::class, 'store']);
    Route::get('/{id}', [OrderController::class, 'show'])->whereNumber('id');
    Route::post('/{id}/payment', [VnPayController::class, 'create'])->whereNumber('id');
    Route::post('/{id}/cancel', [OrderController::class, 'cancel'])->whereNumber('id');
    Route::post('/{id}/confirm', [OrderController::class, 'confirm'])->whereNumber('id');
    Route::post('/{id}/ship', [OrderController::class, 'ship'])->whereNumber('id');
    Route::post('/{id}/complete', [OrderController::class, 'complete'])->whereNumber('id');
});
