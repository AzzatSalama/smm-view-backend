<?php

use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::prefix('payments')->middleware(['auth:sanctum'])->group(function () {
    // Payment management routes (admin/moderator only)
    Route::middleware(['admin.token'])->group(function () {
        Route::get('/', [PaymentController::class, 'index']);
        Route::post('/', [PaymentController::class, 'store']);
        Route::get('/stats', [PaymentController::class, 'getStats']);
        Route::get('/{payment}', [PaymentController::class, 'show']);
        Route::put('/{payment}', [PaymentController::class, 'update']);
        Route::delete('/{payment}', [PaymentController::class, 'destroy']);
        Route::patch('/{payment}/refund', [PaymentController::class, 'refund']);
        Route::patch('/{payment}/retry', [PaymentController::class, 'retry']);
        Route::get('/streamer/{streamerId}', [PaymentController::class, 'getStreamerPayments']);
    });
});
