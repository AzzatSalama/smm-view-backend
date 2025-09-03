<?php

use App\Http\Controllers\StreamerController;
use App\Http\Controllers\WordlistController;
use Illuminate\Support\Facades\Route;

Route::prefix('streamer')->group(function () {
    // Public routes
    Route::post('register', [StreamerController::class, 'register']);
    Route::post('set-password', [StreamerController::class, 'setPassword']);
    Route::post('login', [StreamerController::class, 'login']);
    Route::post('forgot-password', [StreamerController::class, 'forgotPassword']);
    Route::post('resend-setup-email', [StreamerController::class, 'resendSetupEmail']);
    Route::post('validate-token', [StreamerController::class, 'validateToken']);

    // Protected routes (require streamer authentication)
    Route::middleware(['auth:sanctum', 'streamer.token'])->group(function () {
        Route::get('check-auth', [StreamerController::class, 'checkAuth']);
        Route::get('profile', [StreamerController::class, 'profile']);
        Route::post('logout', [StreamerController::class, 'logout']);
        Route::post('change-password', [StreamerController::class, 'changePassword']);

        // Stream Management Routes
        Route::get('streams', [StreamerController::class, 'getStreams']);
        Route::post('streams', [StreamerController::class, 'addStream']);
        Route::put('streams/{streamId}', [StreamerController::class, 'updateStream']);
        Route::delete('streams/{streamId}', [StreamerController::class, 'deleteStream']);
        Route::post('streams/{streamId}/start', [StreamerController::class, 'startStream']);
        Route::post('streams/{streamId}/end', [StreamerController::class, 'endStream']);
        Route::get('streaming-stats', [StreamerController::class, 'getStreamingStats']);
        Route::get('available-wordlists', [StreamerController::class, 'getAvailableWordlists']);

        // Wordlist Management Routes
        Route::get('wordlists', [WordlistController::class, 'getWordlists']);
        Route::get('wordlists/{type}', [WordlistController::class, 'getWordlist']);
        Route::put('wordlists/{type}', [WordlistController::class, 'updateWordlist']);
        Route::delete('wordlists/{type}', [WordlistController::class, 'deleteWordlist']);
    });
});
