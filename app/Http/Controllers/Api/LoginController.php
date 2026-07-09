<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\OtpService;
use App\Support\ApiResponse;
use App\Support\MpinRules;
use App\Support\PhoneRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LoginController extends AuthController
{
    public function config(): JsonResponse
    {
        return ApiResponse::success([
            'otp_length' => (int) config('otp.length', 4),
            'otp_resend_after_seconds' => (int) config('otp.resend_after_seconds', 30),
            'otp_expires_in_seconds' => (int) config('otp.expires_in_seconds', 300),
            'mpin_length' => MpinRules::length(),
        ]);
    }

    public function sendOtp(Request $request, OtpService $otp): JsonResponse
    {
        $data = $request->validate([
            'phone' => PhoneRules::rules(),
        ], PhoneRules::messages());

        $phone = PhoneRules::normalize($data['phone']);
        $user = $this->findLoginUser($phone);

        $result = $otp->sendLoginOtp($phone);
        $message = $result['message'] ?? '';
        unset($result['message']);

        return ApiResponse::success(array_merge($result, [
            'phone' => $phone,
            'requires_otp_verification' => true,
            'requires_mpin' => filled($user->getRawOriginal('mpin')),
            'mpin_length' => MpinRules::length(),
            'has_mpin' => filled($user->getRawOriginal('mpin')),
            'next_api' => '/api/v1/login/verify-otp',
        ]), $message);
    }

    public function resendOtp(Request $request, OtpService $otp): JsonResponse
    {
        return $this->sendOtp($request, $otp);
    }

    public function verifyOtp(Request $request, OtpService $otp): JsonResponse
    {
        $otpLength = (int) config('otp.length', 4);
        $mpinLength = MpinRules::length();

        $data = $request->validate([
            'phone' => PhoneRules::rules(),
            'otp' => ['required', 'string', "digits:{$otpLength}", 'regex:/^\d+$/'],
            'mpin' => ['nullable', 'string', "digits:{$mpinLength}", 'regex:/^\d+$/'],
        ], array_merge(PhoneRules::messages(), MpinRules::validationMessages(), [
            'otp.digits' => "OTP must be exactly {$otpLength} digits.",
            'otp.regex' => 'OTP must contain only numbers.',
        ]));

        $phone = PhoneRules::normalize($data['phone']);
        $user = User::query()->where('phone', $phone)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'phone' => ['This mobile number is not registered. Please sign up first.'],
            ]);
        }

        $otp->verifyLoginOtp($phone, $data['otp']);

        if (blank($data['mpin'] ?? null)) {
            return ApiResponse::success([
                'requires_mpin' => true,
                'phone' => $phone,
                'mpin_length' => $mpinLength,
                'has_mpin' => filled($user->mpin),
                'next_api' => '/api/v1/login/mpin',
                'user' => $this->userPayload($user),
            ], 'OTP verified. Enter your M-PIN on the next screen to login.');
        }

        return $this->loginWithMpin($user, $phone, $data['mpin'], $otp);
    }

    public function verifyMpin(Request $request, OtpService $otp): JsonResponse
    {
        $mpinLength = MpinRules::length();

        $data = $request->validate([
            'phone' => PhoneRules::rules(),
            'mpin' => ['required', 'string', "digits:{$mpinLength}", 'regex:/^\d+$/'],
        ], array_merge(PhoneRules::messages(), MpinRules::validationMessages()));

        $phone = PhoneRules::normalize($data['phone']);

        if (! $otp->phoneWasVerified($phone, 'login')) {
            throw ValidationException::withMessages([
                'phone' => ['Please verify your mobile number before entering your M-PIN.'],
            ]);
        }

        $user = User::query()->where('phone', $phone)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'phone' => ['This mobile number is not registered. Please sign up first.'],
            ]);
        }

        return $this->loginWithMpin($user, $phone, $data['mpin'], $otp);
    }

    protected function findLoginUser(string $phone): User
    {
        $user = User::query()->where('phone', $phone)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'phone' => ['This mobile number is not registered. Please sign up first.'],
            ]);
        }

        if ($user->is_blocked) {
            throw ValidationException::withMessages([
                'phone' => ['Your account has been blocked.'],
            ])->status(403);
        }

        return $user;
    }
}
