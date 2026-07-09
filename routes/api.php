<?php

use App\Http\Controllers\Api\AppConfigController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ForgotMpinController;
use App\Http\Controllers\Api\JewelleryCheckoutController;
use App\Http\Controllers\Api\JewelleryController;
use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\MetalRateController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Controllers\Api\SellJewelleryController;
use App\Http\Controllers\Api\SigController;
use App\Http\Controllers\Api\SupportController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/app/config', [AppConfigController::class, 'index']);
    Route::get('/app/faqs', [AppConfigController::class, 'faqs']);
    Route::get('/app/pages/{slug}', [AppConfigController::class, 'page']);

    Route::get('/rates', [MetalRateController::class, 'index']);

    Route::get('/jewellery/categories', [JewelleryController::class, 'categories']);
    Route::get('/jewellery/sub-categories', [JewelleryController::class, 'subCategories']);
    Route::get('/jewellery/products', [JewelleryController::class, 'products']);
    Route::get('/jewellery/products/{product}', [JewelleryController::class, 'show']);

    Route::get('/register/config', [RegistrationController::class, 'config']);
    Route::post('/register/send-otp', [RegistrationController::class, 'sendOtp']);
    Route::post('/register/resend-otp', [RegistrationController::class, 'resendOtp']);
    Route::post('/register/verify-otp', [RegistrationController::class, 'verifyOtp']);
    Route::post('/register/login-mpin', [RegistrationController::class, 'loginMpin']);
    Route::post('/register/details', [RegistrationController::class, 'details']);
    Route::match(['get', 'post'], '/register/validate-referral', [RegistrationController::class, 'validateReferral']);
    Route::post('/register/mpin', [RegistrationController::class, 'setMpin']);

    Route::post('/register', [AuthController::class, 'register']);

    Route::get('/login/config', [LoginController::class, 'config']);
    Route::post('/login/send-otp', [LoginController::class, 'sendOtp']);
    Route::post('/login/resend-otp', [LoginController::class, 'resendOtp']);
    Route::post('/login/verify-otp', [LoginController::class, 'verifyOtp']);
    Route::post('/login/mpin', [LoginController::class, 'verifyMpin']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::get('/forgot-mpin/config', [ForgotMpinController::class, 'config']);
    Route::post('/forgot-mpin/send-otp', [ForgotMpinController::class, 'sendOtp']);
    Route::post('/forgot-mpin/resend-otp', [ForgotMpinController::class, 'resendOtp']);
    Route::post('/forgot-mpin/verify-otp', [ForgotMpinController::class, 'verifyOtp']);
    Route::post('/forgot-mpin/set-mpin', [ForgotMpinController::class, 'setMpin']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::put('/profile', [ProfileController::class, 'update']);
        Route::post('/profile', [ProfileController::class, 'update']);
        Route::post('/profile/photo', [ProfileController::class, 'updatePhoto']);
        Route::put('/profile/mpin', [ProfileController::class, 'updateMpin']);
        Route::delete('/profile', [ProfileController::class, 'destroy']);
        Route::post('/profile/close-account', [ProfileController::class, 'destroy']);
        Route::get('/referral-stats', [ProfileController::class, 'referralStats']);
        Route::get('/invoices', [ProfileController::class, 'invoices']);
        Route::get('/invoices/{invoice}/download', [ProfileController::class, 'downloadInvoice'])
            ->name('api.invoices.download');
        Route::get('/addresses', [AddressController::class, 'index']);
        Route::post('/addresses', [AddressController::class, 'store']);
        Route::get('/addresses/{address}', [AddressController::class, 'show']);
        Route::put('/addresses/{address}', [AddressController::class, 'update']);
        Route::post('/addresses/{address}/default', [AddressController::class, 'setDefault']);
        Route::delete('/addresses/{address}', [AddressController::class, 'destroy']);

        Route::get('/jewellery/checkout/summary', [JewelleryCheckoutController::class, 'summary']);
        Route::post('/jewellery/checkout/buy-now', [JewelleryCheckoutController::class, 'buyNow']);

        Route::get('/sell-jewellery/config', [SellJewelleryController::class, 'config']);
        Route::post('/sell-jewellery/estimate', [SellJewelleryController::class, 'estimate']);
        Route::get('/sell-jewellery/recent', [SellJewelleryController::class, 'recent']);
        Route::post('/sell-jewellery/requests', [SellJewelleryController::class, 'store']);
        Route::get('/sell-jewellery/requests', [SellJewelleryController::class, 'index']);
        Route::get('/sell-jewellery/requests/{booking}', [SellJewelleryController::class, 'show']);

        Route::get('/support/config', [SupportController::class, 'config']);
        Route::get('/support/tickets', [SupportController::class, 'index']);
        Route::post('/support/tickets', [SupportController::class, 'store']);
        Route::get('/support/tickets/{ticket}', [SupportController::class, 'show']);
        Route::post('/support/tickets/{ticket}/replies', [SupportController::class, 'reply']);

        Route::get('/sig/config', [SigController::class, 'config']);
        Route::get('/sig', [SigController::class, 'show']);
        Route::get('/sig/transactions', [SigController::class, 'transactions']);
        Route::post('/sig/activate', [SigController::class, 'activate']);
        Route::post('/sig/pause', [SigController::class, 'pause']);
        Route::post('/sig/resume', [SigController::class, 'resume']);
        Route::post('/sig/stop', [SigController::class, 'stop']);
    });
});
