<?php

use App\Modules\Auth\Http\Middleware\AuthenticateJwt;
use App\Modules\Wallet\Http\Controllers\WalletController;
use App\Modules\Wallet\Http\Controllers\SellerRevenueController;
use App\Modules\Wallet\Http\Controllers\WalletVnPayController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', AuthenticateJwt::class])->prefix('api/v1/me/wallet')->group(function (): void {
    Route::get('/', [WalletController::class, 'show']);
    Route::get('/revenue', SellerRevenueController::class);
    Route::get('/bank-account', [WalletController::class, 'bankAccount']);
    Route::put('/bank-account', [WalletController::class, 'saveBankAccount'])->middleware('throttle:wallet-write');
    Route::post('/vnpay-deposits', [WalletVnPayController::class, 'create'])->middleware('throttle:wallet-write');
    Route::get('/vnpay-deposits/{reference}', [WalletVnPayController::class, 'show'])->whereAlphaNumeric('reference');
    Route::post('/withdrawals', [WalletController::class, 'withdraw'])->middleware('throttle:wallet-write');
    Route::post('/withdrawals/{id}/cancel', [WalletController::class, 'cancelWithdrawal'])->whereNumber('id')->middleware('throttle:wallet-write');
});
