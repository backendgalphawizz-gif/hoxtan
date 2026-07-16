<?php

use App\Http\Controllers\Api\Driver\DriverNotificationController;
use App\Http\Controllers\Api\Driver\DriverAppConfigController;
use App\Http\Controllers\Api\Driver\DriverAuthController;
use App\Http\Controllers\Api\Driver\DriverDeliveriesController;
use App\Http\Controllers\Api\Driver\DriverDeliveryController;
use App\Http\Controllers\Api\Driver\DriverHomeController;
use App\Http\Controllers\Api\Driver\DriverPickupController;
use App\Http\Controllers\Api\Driver\DriverProfileController;
use App\Http\Controllers\Api\AppConfigController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ForgotMpinController;
use App\Http\Controllers\Api\GoalController;
use App\Http\Controllers\Api\HoldingsController;
use App\Http\Controllers\Api\JewelleryCheckoutController;
use App\Http\Controllers\Api\JewelleryController;
use App\Http\Controllers\Api\KycController;
use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\MetalPurchaseController;
use App\Http\Controllers\Api\MetalRateController;
use App\Http\Controllers\Api\MetalWithdrawalController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Controllers\Api\SellJewelleryController;
use App\Http\Controllers\Api\SigController;
use App\Http\Controllers\Api\SupportController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Broadcast::routes(['middleware' => ['auth:sanctum']]);

Route::prefix('v1')->group(function (): void {
    Route::get('/app/config', [AppConfigController::class, 'index']);
    Route::get('/app/faqs', [AppConfigController::class, 'faqs']);
    Route::get('/app/pages/{slug}', [AppConfigController::class, 'page']);

    Route::get('/rates', [MetalRateController::class, 'index']);
    Route::get('/rates/realtime-config', [MetalRateController::class, 'realtimeConfig']);
    // rates/push requires auth — moved into auth:sanctum group (returns wallet after purchase)

    Route::get('/jewellery/categories', [JewelleryController::class, 'categories']);
    Route::get('/jewellery/sub-categories', [JewelleryController::class, 'subCategories']);
    Route::get('/jewellery/sub-sub-categories', [JewelleryController::class, 'subSubCategories']);
    Route::get('/jewellery/filters', [JewelleryController::class, 'filters']);
    Route::get('/jewellery/emi-plans', [JewelleryController::class, 'emiPlans']);
    Route::get('/jewellery/products', [JewelleryController::class, 'products']);
    Route::get('/jewellery/products/{product}', [JewelleryController::class, 'show']);
    Route::get('/products/{product}', [JewelleryController::class, 'show']);

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

    Route::prefix('driver')->group(function (): void {
        Route::get('/app/config', [DriverAppConfigController::class, 'index']);
        Route::get('/login/config', [DriverAuthController::class, 'config']);
        Route::post('/login/send-otp', [DriverAuthController::class, 'sendOtp']);
        Route::post('/login/resend-otp', [DriverAuthController::class, 'resendOtp']);
        Route::post('/login/verify-otp', [DriverAuthController::class, 'verifyOtp']);

        Route::middleware(['auth:sanctum', 'driver.api'])->group(function (): void {
            Route::post('/logout', [DriverAuthController::class, 'logout']);
            Route::get('/profile', [DriverProfileController::class, 'show']);
            Route::put('/profile', [DriverProfileController::class, 'update']);
            Route::post('/profile', [DriverProfileController::class, 'update']);
            Route::put('/availability', [DriverProfileController::class, 'updateAvailability']);
            Route::post('/availability', [DriverProfileController::class, 'updateAvailability']);
            Route::get('/home', [DriverHomeController::class, 'home']);
            Route::get('/statistics', [DriverHomeController::class, 'statistics']);
            Route::get('/tasks', [DriverHomeController::class, 'tasks']);
            Route::get('/deliveries', [DriverDeliveriesController::class, 'index']);
            Route::get('/deliveries/config', [DriverDeliveryController::class, 'config']);
            Route::get('/tasks/deliveries/{order}', [DriverDeliveryController::class, 'show']);
            Route::post('/tasks/deliveries/{order}/picked-up', [DriverDeliveryController::class, 'markPickedUp']);
            Route::post('/tasks/deliveries/{order}/verify-delivery', [DriverDeliveryController::class, 'verifyDelivery']);
            Route::post('/tasks/deliveries/{order}/unable-to-deliver', [DriverDeliveryController::class, 'markUnableToDeliver']);
            Route::get('/pickups/config', [DriverPickupController::class, 'config']);
            Route::get('/tasks/pickups/{booking}', [DriverPickupController::class, 'show'])
                ->where('booking', '[A-Za-z0-9#_-]+');
            Route::post('/tasks/pickups/{booking}/verify-customer', [DriverPickupController::class, 'verifyCustomer'])
                ->where('booking', '[A-Za-z0-9#_-]+');
            Route::post('/tasks/pickups/{booking}/upload-proof', [DriverPickupController::class, 'uploadProof'])
                ->where('booking', '[A-Za-z0-9#_-]+');
            Route::post('/tasks/pickups/{booking}/verify-otp', [DriverPickupController::class, 'verifyOtp'])
                ->where('booking', '[A-Za-z0-9#_-]+');
            Route::post('/tasks/pickups/{booking}/unable-to-pickup', [DriverPickupController::class, 'markUnableToPickup'])
                ->where('booking', '[A-Za-z0-9#_-]+');

            Route::get('/notifications', [DriverNotificationController::class, 'index']);
            Route::get('/notifications/unread-count', [DriverNotificationController::class, 'unreadCount']);
            Route::post('/notifications/read-all', [DriverNotificationController::class, 'markAllRead']);
            Route::post('/notifications/{notification}/read', [DriverNotificationController::class, 'markRead'])
                ->whereNumber('notification');
        });
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        // Wallet + live rates after purchase (Bearer token REQUIRED).
        Route::post('/rates/push', [MetalRateController::class, 'push']);
        Route::get('/rates/push', [MetalRateController::class, 'push']);

        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::get('/profile/assets', [ProfileController::class, 'assets']);
        Route::get('/assets/balance', [ProfileController::class, 'assets']);
        Route::put('/profile', [ProfileController::class, 'update']);
        Route::post('/profile', [ProfileController::class, 'update']);
        Route::post('/profile/photo', [ProfileController::class, 'updatePhoto']);
        Route::put('/profile/mpin', [ProfileController::class, 'updateMpin']);
        Route::delete('/profile', [ProfileController::class, 'destroy']);
        Route::post('/profile/close-account', [ProfileController::class, 'closeAccount']);
        Route::post('/profile/close', [ProfileController::class, 'closeAccount']);
        Route::get('/referral-stats', [ProfileController::class, 'referralStats']);
        Route::get('/invoices', [ProfileController::class, 'invoices']);
        Route::get('/invoices/{invoice}/download', [ProfileController::class, 'downloadInvoice'])
            ->name('api.invoices.download');
        Route::get('/certificates/{certificate}/download', [ProfileController::class, 'downloadCertificate'])
            ->name('api.certificates.download');

        Route::get('/orders/config', [OrderController::class, 'config']);
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{order}', [OrderController::class, 'show']);
        Route::get('/orders/{order}/emi-cancel-preview', [OrderController::class, 'cancelEmiPreview']);
        Route::post('/orders/{order}/emi-cancel', [OrderController::class, 'cancelEmi']);

        Route::get('/transactions/config', [TransactionController::class, 'config']);
        Route::get('/transactions', [TransactionController::class, 'index']);
        Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])
            ->where('transaction', '[A-Za-z0-9:_-]+');

        Route::get('/kyc/config', [KycController::class, 'config']);
        Route::get('/kyc', [KycController::class, 'show']);
        Route::post('/kyc/pan/request-otp', [KycController::class, 'requestPanOtp']);
        Route::post('/kyc/pan/verify-otp', [KycController::class, 'verifyPanOtp']);
        Route::post('/kyc/pan/verify', [KycController::class, 'verifyPan']);
        Route::post('/kyc/aadhaar/request-otp', [KycController::class, 'requestAadhaarOtp']);
        Route::post('/kyc/aadhaar/verify-otp', [KycController::class, 'verifyAadhaarOtp']);
        Route::post('/kyc/face', [KycController::class, 'submitFace']);
        Route::post('/kyc/bank', [KycController::class, 'submitBank']);
        Route::post('/kyc/bank/verify', [KycController::class, 'submitBank']);

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
        Route::post('/sig/estimate', [SigController::class, 'estimate']);
        Route::get('/sig/transactions', [SigController::class, 'transactions']);
        Route::post('/sig/activate', [SigController::class, 'activate']);
        Route::post('/sig/pause', [SigController::class, 'pause']);
        Route::post('/sig/resume', [SigController::class, 'resume']);
        Route::post('/sig/stop', [SigController::class, 'stop']);

        Route::get('/buy-metal/config', [MetalPurchaseController::class, 'config']);
        Route::post('/buy-metal/estimate', [MetalPurchaseController::class, 'estimate']);
        Route::post('/buy-metal/purchase', [MetalPurchaseController::class, 'purchase']);

        Route::get('/holdings/config', [HoldingsController::class, 'config']);
        Route::get('/holdings', [HoldingsController::class, 'index']);
        Route::get('/holdings/performance', [HoldingsController::class, 'performance']);
        Route::get('/holdings/transactions', [HoldingsController::class, 'transactions']);
        Route::post('/holdings/purchase', [HoldingsController::class, 'purchase']);
        Route::post('/holdings/sell', [HoldingsController::class, 'sell']);
        Route::post('/holdings/claim-bonus', [HoldingsController::class, 'claimBonus']);

        Route::get('/withdraw/assets', [MetalWithdrawalController::class, 'assets']);
        Route::get('/withdraw/{asset}/screen', [MetalWithdrawalController::class, 'screen']);
        Route::post('/withdraw/estimate', [MetalWithdrawalController::class, 'estimate']);
        Route::post('/withdraw', [MetalWithdrawalController::class, 'store']);
        Route::get('/withdrawals', [MetalWithdrawalController::class, 'index']);
        Route::get('/withdrawals/{withdrawal}', [MetalWithdrawalController::class, 'show']);

        Route::get('/goals/config', [GoalController::class, 'config']);
        Route::get('/goals', [GoalController::class, 'index']);
        Route::post('/goals', [GoalController::class, 'store']);
        Route::get('/goals/{goal}', [GoalController::class, 'show']);
        Route::put('/goals/{goal}', [GoalController::class, 'update']);
        Route::post('/goals/{goal}', [GoalController::class, 'update']);
        Route::delete('/goals/{goal}', [GoalController::class, 'destroy']);

        Route::post('/device-token', [NotificationController::class, 'registerDevice']);
        Route::delete('/device-token', [NotificationController::class, 'removeDevice']);
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])
            ->whereNumber('notification');
    });
});
