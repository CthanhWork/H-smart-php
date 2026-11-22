<?php

use App\Modules\Assistant\Http\Controllers\AssistantController;
use App\Modules\Auth\Http\Middleware\AuthenticateJwt;
use App\Modules\Auth\Http\Middleware\AuthenticateJwtOptional;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', AuthenticateJwtOptional::class])->post('api/v1/assistant/chat', [AssistantController::class, 'chat'])->middleware('throttle:assistant-chat');

Route::middleware(['api', AuthenticateJwt::class])->prefix('api/v1/assistant')->group(function (): void {
    Route::get('conversations', [AssistantController::class, 'index']);
    Route::get('conversations/{conversationId}', [AssistantController::class, 'show'])->whereNumber('conversationId');
    Route::delete('conversations/{conversationId}', [AssistantController::class, 'destroy'])->whereNumber('conversationId');
    Route::post('analyze-image', [AssistantController::class, 'analyzeImage'])->middleware('throttle:assistant-image');
    Route::post('generate-listing-description', [AssistantController::class, 'generateListingDescription'])->middleware('throttle:assistant-description');
});
