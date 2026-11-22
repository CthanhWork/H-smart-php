<?php

use App\Modules\Auth\Http\Middleware\AuthenticateJwt;
use App\Modules\Wallet\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', AuthenticateJwt::class])->prefix('api/v1/me/wallet')->group(function (): void {
    Route::get('/', [WalletController::class, 'show']);
    Route::post('/simulated-deposits', [WalletController::class, 'simulatedDeposit'])->middleware('throttle:wallet-write');
    Route::post('/withdrawals', [WalletController::class, 'withdraw'])->middleware('throttle:wallet-write');
});
