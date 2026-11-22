<?php

use App\Modules\Product\Http\Controllers\CreateProductController;
use App\Modules\Product\Http\Controllers\CategoryController;
use App\Modules\Product\Http\Controllers\FavoriteController;
use App\Modules\Product\Http\Controllers\ProductController;
use App\Modules\Product\Http\Controllers\CartController;
use App\Modules\Product\Http\Controllers\ProductPublicationController;
use App\Modules\Product\Http\Controllers\ProductPromotionController;
use App\Modules\Product\Http\Controllers\PublicMarketplaceSettingsController;
use App\Modules\Product\Http\Controllers\SellerController;
use App\Modules\Product\Http\Controllers\UpdateProductStatusController;
use App\Modules\Admin\Http\Controllers\ReportSubmissionController;
use Illuminate\Support\Facades\Route;

Route::middleware('api')->prefix('api/v1')->group(function (): void {
    Route::get('marketplace-settings', PublicMarketplaceSettingsController::class);
    Route::get('products', [ProductController::class, 'index'])->middleware('auth.jwt.optional');
    Route::get('sellers/{id}', [SellerController::class, 'show'])->whereNumber('id');
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('products/{id}', [ProductController::class, 'show'])->whereNumber('id')->middleware('auth.jwt.optional');

    Route::middleware('auth.jwt')->group(function (): void {
        Route::get('me/cart', [CartController::class, 'index']);
        Route::post('me/cart', [CartController::class, 'store']);
        Route::delete('me/cart/{id}', [CartController::class, 'destroy'])->whereNumber('id');
        Route::post('reports', ReportSubmissionController::class);
        Route::get('me/favorites', [FavoriteController::class, 'index']);
        Route::post('me/favorites', [FavoriteController::class, 'store']);
        Route::delete('me/favorites/{productId}', [FavoriteController::class, 'destroy'])->whereNumber('productId');
        Route::get('me/products', [ProductController::class, 'mine']);
        Route::get('me/listing-quota', [ProductPublicationController::class, 'quota']);
        Route::get('me/promotion-packages', [ProductPromotionController::class, 'packages']);
        Route::get('me/products/{id}/publication-quote', [ProductPublicationController::class, 'quote'])->whereNumber('id');
        Route::post('me/products/{id}/publication-payment', [ProductPublicationController::class, 'pay'])->whereNumber('id');
        Route::post('me/products/{id}/promotions', [ProductPromotionController::class, 'purchase'])->whereNumber('id');
        Route::post('products', CreateProductController::class)->middleware('throttle:product-create');
        Route::patch('products/{id}', [ProductController::class, 'update'])->whereNumber('id');
        Route::patch('products/{id}/status', UpdateProductStatusController::class)->whereNumber('id');
        Route::delete('products/{id}', [ProductController::class, 'destroy'])->whereNumber('id');
    });
});
