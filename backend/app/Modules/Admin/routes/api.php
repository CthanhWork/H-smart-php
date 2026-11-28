<?php

use App\Modules\Admin\Http\Controllers\AdminAuditLogController;
use App\Modules\Admin\Http\Controllers\AdminCategoryController;
use App\Modules\Admin\Http\Controllers\AdminCodController;
use App\Modules\Admin\Http\Controllers\AdminDashboardController;
use App\Modules\Admin\Http\Controllers\AdminDeliveryController;
use App\Modules\Admin\Http\Controllers\AdminDisputeController;
use App\Modules\Admin\Http\Controllers\AdminMarketplaceSettingsController;
use App\Modules\Admin\Http\Controllers\AdminOrderController;
use App\Modules\Admin\Http\Controllers\AdminPaymentReconciliationController;
use App\Modules\Admin\Http\Controllers\AdminProductApproveController;
use App\Modules\Admin\Http\Controllers\AdminProductHideController;
use App\Modules\Admin\Http\Controllers\AdminProductPendingController;
use App\Modules\Admin\Http\Controllers\AdminPromotionController;
use App\Modules\Admin\Http\Controllers\AdminReportController;
use App\Modules\Admin\Http\Controllers\AdminRevenueController;
use App\Modules\Admin\Http\Controllers\AdminUserController;
use App\Modules\Admin\Http\Controllers\AdminUserRestoreController;
use App\Modules\Admin\Http\Controllers\AdminUserSuspendController;
use App\Modules\Admin\Http\Controllers\AdminWithdrawalController;
use App\Modules\Admin\Http\Middleware\EnsureAdmin;
use App\Modules\Auth\Http\Middleware\AuthenticateJwt;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/admin')
    ->middleware([AuthenticateJwt::class, EnsureAdmin::class])
    ->group(function (): void {
        Route::get('stats', AdminDashboardController::class);
        Route::get('revenue', AdminRevenueController::class);
        Route::get('orders', AdminOrderController::class);
        Route::post('orders/{id}/release-to-wallet', [AdminOrderController::class, 'release'])->whereNumber('id');
        Route::post('orders/{id}/cod', [AdminCodController::class, 'update'])->whereNumber('id');
        Route::post('orders/{id}/delivery-confirmation', [AdminDeliveryController::class, 'confirm'])->whereNumber('id');
        Route::get('reports', [AdminReportController::class, 'index']);
        Route::post('reports/{id}/resolve', [AdminReportController::class, 'resolve'])->whereNumber('id');
        Route::get('audit-logs', AdminAuditLogController::class);
        Route::get('withdrawals', [AdminWithdrawalController::class, 'index']);
        Route::get('withdrawals/{id}', [AdminWithdrawalController::class, 'show'])->whereNumber('id');
        Route::post('withdrawals/{id}/processing', [AdminWithdrawalController::class, 'markProcessing'])->whereNumber('id');
        Route::post('withdrawals/{id}/reconciliation', [AdminWithdrawalController::class, 'markReconciliation'])->whereNumber('id');
        Route::post('withdrawals/{id}/reject', [AdminWithdrawalController::class, 'reject'])->whereNumber('id');
        Route::post('withdrawals/{id}/outcome', [AdminWithdrawalController::class, 'confirmOutcome'])->whereNumber('id');
        Route::get('promotions', [AdminPromotionController::class, 'index']);
        Route::get('marketplace-settings', [AdminMarketplaceSettingsController::class, 'show']);
        Route::patch('marketplace-settings', [AdminMarketplaceSettingsController::class, 'update']);
        Route::get('payment-reconciliations', [AdminPaymentReconciliationController::class, 'index']);
        // Legacy VietQR reconciliation may be read for audit, but cannot create
        // new bank-backed payment confirmations in the Sandbox-only project.
        Route::post('payment-reconciliations', fn () => response()->json([
            'status' => 'error',
            'message' => 'Đối soát VietQR chỉ còn tra cứu lịch sử; không nhận ghi nhận giao dịch mới.',
        ], 410));
        Route::get('disputes', [AdminDisputeController::class, 'index']);
        Route::post('disputes/{id}/resolve', [AdminDisputeController::class, 'resolve'])->whereNumber('id');
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
