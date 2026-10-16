<?php

use App\Modules\Admin\Http\Controllers\AdminProductApproveController;
use App\Modules\Admin\Http\Controllers\AdminProductHideController;
use App\Modules\Admin\Http\Controllers\AdminProductPendingController;
use App\Modules\Admin\Http\Controllers\AdminUserController;
use App\Modules\Admin\Http\Controllers\AdminUserRestoreController;
use App\Modules\Admin\Http\Controllers\AdminUserSuspendController;
use App\Modules\Admin\Http\Controllers\AdminCategoryController;
use App\Modules\Admin\Http\Middleware\EnsureAdmin;
use App\Modules\Auth\Http\Middleware\AuthenticateJwt;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/admin')
    ->middleware([AuthenticateJwt::class, EnsureAdmin::class])
    ->group(function (): void {
        // Product moderation
        Route::get('products/pending', AdminProductPendingController::class);
        Route::post('products/{productId}/approve', AdminProductApproveController::class);
        Route::post('products/{productId}/hide', AdminProductHideController::class);

        // User management
        Route::get('users', AdminUserController::class);
        Route::post('users/{userId}/suspend', AdminUserSuspendController::class);
        Route::post('users/{userId}/restore', AdminUserRestoreController::class);

        // Product categories
        Route::get('categories', [AdminCategoryController::class, 'index']);
        Route::post('categories', [AdminCategoryController::class, 'store']);
        Route::patch('categories/{id}', [AdminCategoryController::class, 'update'])->whereNumber('id');
        Route::delete('categories/{id}', [AdminCategoryController::class, 'destroy'])->whereNumber('id');
    });
