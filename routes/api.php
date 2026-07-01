<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::put('/profile', [ProfileController::class, 'update']);
        Route::put('/profile/mpin', [ProfileController::class, 'updateMpin']);
        Route::get('/referral-stats', [ProfileController::class, 'referralStats']);
        Route::get('/invoices', [ProfileController::class, 'invoices']);
        Route::get('/invoices/{invoice}/download', [ProfileController::class, 'downloadInvoice'])
            ->name('api.invoices.download');
    });
});
