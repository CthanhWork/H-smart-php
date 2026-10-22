<?php

use App\Modules\Product\Http\Controllers\CreateProductController;
use App\Modules\Product\Http\Controllers\CategoryController;
use App\Modules\Product\Http\Controllers\FavoriteController;
use App\Modules\Product\Http\Controllers\ProductController;
use App\Modules\Product\Http\Controllers\UpdateProductStatusController;
use App\Modules\Admin\Http\Controllers\ReportSubmissionController;
use Illuminate\Support\Facades\Route;

Route::middleware('api')->prefix('api/v1')->group(function (): void {
    Route::get('products', [ProductController::class, 'index'])->middleware('auth.jwt.optional');
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('products/{id}', [ProductController::class, 'show'])->whereNumber('id')->middleware('auth.jwt.optional');

    Route::middleware('auth.jwt')->group(function (): void {
        Route::post('reports', ReportSubmissionController::class);
        Route::get('me/favorites', [FavoriteController::class, 'index']);
        Route::post('me/favorites', [FavoriteController::class, 'store']);
        Route::delete('me/favorites/{productId}', [FavoriteController::class, 'destroy'])->whereNumber('productId');
        Route::get('me/products', [ProductController::class, 'mine']);
        Route::post('products', CreateProductController::class)->middleware('throttle:product-create');
        Route::patch('products/{id}', [ProductController::class, 'update'])->whereNumber('id');
        Route::patch('products/{id}/status', UpdateProductStatusController::class)->whereNumber('id');
        Route::delete('products/{id}', [ProductController::class, 'destroy'])->whereNumber('id');
    });
});
