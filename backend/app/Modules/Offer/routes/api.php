<?php

use App\Modules\Offer\Http\Controllers\AcceptOfferController;
use App\Modules\Offer\Http\Controllers\CancelOfferController;
use App\Modules\Offer\Http\Controllers\CreateOfferController;
use App\Modules\Offer\Http\Controllers\OfferController;
use App\Modules\Offer\Http\Controllers\RejectOfferController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'auth.jwt'])->prefix('api/v1')->group(function (): void {
    Route::get('offers', [OfferController::class, 'index']);
    Route::get('offers/{id}', [OfferController::class, 'show'])->whereNumber('id');
    Route::post('offers', CreateOfferController::class);
    Route::post('offers/{id}/accept', AcceptOfferController::class)->whereNumber('id');
    Route::post('offers/{id}/reject', RejectOfferController::class)->whereNumber('id');
    Route::post('offers/{id}/cancel', CancelOfferController::class)->whereNumber('id');
});
