<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Services\FirebaseCloudMessagingService;
use App\Services\OtpService;
use App\Support\ApiResponse;
use App\Support\DriverPayload;
use App\Support\FcmTokenRequest;
use App\Support\PhoneRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DriverAuthController extends Controller
{
    public function config(): JsonResponse
    {
        return ApiResponse::success([
            'app_name' => config('driver.app_name', 'HOXTAN Driver'),
            'login' => config('driver.login', []),
            'vehicle_types' => config('driver.vehicle_types', []),
            'otp_length' => (int) config('otp.length', 4),
            'otp_resend_after_seconds' => (int) config('otp.resend_after_seconds', 30),
            'otp_expires_in_seconds' => (int) config('otp.expires_in_seconds', 300),
            'country_code' => '+91',
        ]);
    }

    public function sendOtp(Request $request, OtpService $otp): JsonResponse
    {
        $data = $request->validate([
            'phone' => PhoneRules::rules(),
        ], PhoneRules::messages());

        $phone = PhoneRules::normalize($data['phone']);
        $result = $otp->sendDriverLoginOtp($phone);
        $message = $result['message'] ?? '';
        unset($result['message']);

        return ApiResponse::success(array_merge($result, [
            'phone' => $phone,
            'phone_display' => '+91 '.$phone,
            'next_api' => '/api/v1/driver/login/verify-otp',
        ]), $message);
    }

    public function resendOtp(Request $request, OtpService $otp): JsonResponse
    {
        return $this->sendOtp($request, $otp);
    }

    public function verifyOtp(Request $request, OtpService $otp, FirebaseCloudMessagingService $fcm): JsonResponse
    {
        $otpLength = (int) config('otp.length', 4);

        $data = $request->validate(array_merge([
            'phone' => PhoneRules::rules(),
            'otp' => ['required', 'string', "digits:{$otpLength}", 'regex:/^\d+$/'],
        ], FcmTokenRequest::validationRules()), array_merge(PhoneRules::messages(), [
            'otp.digits' => "OTP must be exactly {$otpLength} digits.",
            'otp.regex' => 'OTP must contain only numbers.',
        ]));

        $phone = PhoneRules::normalize($data['phone']);
        $otp->verifyDriverLoginOtp($phone, $data['otp']);

        /** @var Driver $driver */
        $driver = Driver::query()->where('phone', $phone)->firstOrFail();
        $driver->update(['last_login_at' => now()]);

        $driver->tokens()->delete();
        $token = $driver->createToken('driver-app')->plainTextToken;

        $fcmToken = FcmTokenRequest::from($request);
        $fcmRegistered = false;
        $deviceTokenId = null;

        if ($fcmToken !== null) {
            try {
                $device = $fcm->registerToken(
                    $driver,
                    $fcmToken,
                    FcmTokenRequest::platform($request),
                    FcmTokenRequest::deviceName($request),
                );
                $fcmRegistered = true;
                $deviceTokenId = $device->id;
            } catch (\Throwable $e) {
                Log::error('Driver FCM token save failed', [
                    'driver_id' => $driver->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ApiResponse::success([
            'token' => $token,
            'token_type' => 'Bearer',
            'fcm_token_registered' => $fcmRegistered,
            'fcm_token_skipped_reason' => $fcmToken === null ? 'empty_or_missing' : ($fcmRegistered ? null : 'save_failed'),
            'device_token_id' => $deviceTokenId,
            'driver' => DriverPayload::make($driver->fresh()),
        ], 'Login successful.');
    }

    public function registerDevice(Request $request, FirebaseCloudMessagingService $fcm): JsonResponse
    {
        $data = $request->validate([
            'token' => ['nullable', 'string', 'max:4096'],
            'fcm_token' => ['nullable', 'string', 'max:4096'],
            'platform' => ['nullable', 'string', 'in:android,ios,web'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $fcmToken = FcmTokenRequest::from($request) ?? (filled($data['token'] ?? null) ? trim((string) $data['token']) : null);

        if ($fcmToken === null) {
            return ApiResponse::error('FCM token is required.', [
                'errors' => ['fcm_token' => ['Please provide fcm_token or token.']],
            ], 422);
        }

        /** @var Driver $driver */
        $driver = $request->user();

        $device = $fcm->registerToken(
            $driver,
            $fcmToken,
            FcmTokenRequest::platform($request) ?? ($data['platform'] ?? null),
            FcmTokenRequest::deviceName($request) ?? ($data['device_name'] ?? null),
        );

        return ApiResponse::success([
            'device_token' => [
                'id' => $device->id,
                'platform' => $device->platform,
                'device_name' => $device->device_name,
                'updated_at' => optional($device->updated_at)?->toIso8601String(),
            ],
            'fcm_token_registered' => true,
            'device_token_id' => $device->id,
        ], 'Device token registered.');
    }

    public function removeDevice(Request $request, FirebaseCloudMessagingService $fcm): JsonResponse
    {
        $request->validate([
            'token' => ['nullable', 'string', 'max:4096'],
            'fcm_token' => ['nullable', 'string', 'max:4096'],
        ]);

        $fcmToken = FcmTokenRequest::from($request);
        if ($fcmToken === null) {
            return ApiResponse::error('FCM token is required.', [
                'errors' => ['fcm_token' => ['Please provide fcm_token or token.']],
            ], 422);
        }

        /** @var Driver $driver */
        $driver = $request->user();
        $fcm->removeToken($driver, $fcmToken);

        return ApiResponse::success([], 'Device token removed.');
    }

    public function logout(Request $request, FirebaseCloudMessagingService $fcm): JsonResponse
    {
        $request->validate(FcmTokenRequest::validationRules());

        /** @var Driver|null $driver */
        $driver = $request->user();
        $fcmToken = FcmTokenRequest::from($request);

        if ($driver instanceof Driver && $fcmToken !== null) {
            $fcm->removeToken($driver, $fcmToken);
        }

        $driver?->currentAccessToken()?->delete();

        return ApiResponse::success([], 'Logged out successfully.');
    }
}
