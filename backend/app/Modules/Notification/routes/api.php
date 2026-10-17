<?php

use App\Modules\Auth\Http\Middleware\AuthenticateJwt;
use App\Modules\Notification\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', AuthenticateJwt::class])->prefix('api/v1/notifications')->group(function (): void {
    Route::get('/', [NotificationController::class, 'index']);
    Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch('/read-all', [NotificationController::class, 'markAllRead']);
    Route::patch('/{id}/read', [NotificationController::class, 'markRead'])->whereNumber('id');
});
