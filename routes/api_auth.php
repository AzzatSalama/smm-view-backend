<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    // Public authentication routes
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register-streamer', [AuthController::class, 'registerStreamer']);
    Route::post('set-password', [AuthController::class, 'setPassword']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
    Route::post('validate-reset-token', [AuthController::class, 'validateResetToken']);
    Route::post('validate-setup-token', [AuthController::class, 'validateSetupToken']);
    Route::post('resend-setup-email', [AuthController::class, 'resendSetupEmail']);
});

// Public API routes (no authentication required)
Route::get('plans/active', [AdminController::class, 'getActivePlans']);

Route::prefix('auth')->group(function () {
    // Protected authentication routes (require any valid token)
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('profile', [AuthController::class, 'profile']);
        Route::get('check-auth', [AuthController::class, 'checkAuth']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
    });
});
