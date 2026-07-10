<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Services\OtpService;
use App\Support\ApiResponse;
use App\Support\DriverPayload;
use App\Support\PhoneRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function verifyOtp(Request $request, OtpService $otp): JsonResponse
    {
        $otpLength = (int) config('otp.length', 4);

        $data = $request->validate([
            'phone' => PhoneRules::rules(),
            'otp' => ['required', 'string', "digits:{$otpLength}", 'regex:/^\d+$/'],
        ], array_merge(PhoneRules::messages(), [
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

        return ApiResponse::success([
            'token' => $token,
            'token_type' => 'Bearer',
            'driver' => DriverPayload::make($driver->fresh()),
        ], 'Login successful.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return ApiResponse::success([], 'Logged out successfully.');
    }
}
