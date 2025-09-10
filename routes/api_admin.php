<?php

use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function () {
    // Public admin routes
    Route::post('login', [AdminController::class, 'login']);

    
    Route::get('{streamer}/plan-price', [AdminController::class, 'getStreamerPlanPrice']);

    // Protected admin routes
    Route::middleware(['auth:sanctum', 'admin.token'])->group(function () {
        Route::get('profile', [AdminController::class, 'profile']);
        Route::post('logout', [AdminController::class, 'logout']);
        
        // Dashboard statistics
        Route::get('dashboard/stats', [AdminController::class, 'getDashboardStats']);
        
        // User management routes
        Route::prefix('users')->group(function () {
            Route::get('/', [AdminController::class, 'getUsers']);
            Route::post('/', [AdminController::class, 'createUser']);
            Route::put('{user}', [AdminController::class, 'updateUser']);
            Route::delete('{user}', [AdminController::class, 'deleteUser']);
            Route::patch('{user}/toggle-status', [AdminController::class, 'toggleUserStatus']);
        });
        
        // Streamer management routes
        Route::prefix('streamers')->group(function () {
            Route::get('/', [AdminController::class, 'getStreamers']);
            Route::get('{streamer}', [AdminController::class, 'getStreamerDetails']);
            Route::patch('{streamer}/toggle-status', [AdminController::class, 'toggleStreamerStatus']);
            Route::put('{streamer}/subscriptions/{subscriptionId}', [AdminController::class, 'updateStreamerSubscription']);
        });
        
        // Subscription plan management routes
        Route::prefix('plans')->group(function () {
            Route::get('/', [AdminController::class, 'getPlans']);
            Route::post('/', [AdminController::class, 'createPlan']);
            Route::put('{plan}', [AdminController::class, 'updatePlan']);
            Route::delete('{plan}', [AdminController::class, 'deletePlan']);
            Route::patch('{plan}/toggle-status', [AdminController::class, 'togglePlanStatus']);
        });
        
        // Payment management routes
        Route::prefix('payments')->group(function () {
            Route::get('/', [AdminController::class, 'getPayments']);
            Route::patch('{payment}/refund', [AdminController::class, 'refundPayment']);
            Route::patch('{payment}/retry', [AdminController::class, 'retryPayment']);
        });
    });
});
