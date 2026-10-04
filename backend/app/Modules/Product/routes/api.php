<?php

use App\Modules\Product\Http\Controllers\CreateProductController;
use App\Modules\Product\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'auth.jwt'])->prefix('api/v1')->group(function (): void {
    Route::get('products', [ProductController::class, 'index']);
    Route::get('me/products', [ProductController::class, 'mine']);
    Route::post('products', CreateProductController::class)->middleware('throttle:product-create');
    Route::get('products/{id}', [ProductController::class, 'show'])->whereNumber('id');
    Route::patch('products/{id}', [ProductController::class, 'update'])->whereNumber('id');
    Route::delete('products/{id}', [ProductController::class, 'destroy'])->whereNumber('id');
});
