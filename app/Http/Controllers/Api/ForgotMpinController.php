<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\MpinResetSessionService;
use App\Services\OtpService;
use App\Support\ApiResponse;
use App\Support\MpinRules;
use App\Support\PhoneRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ForgotMpinController extends AuthController
{
    public function config(): JsonResponse
    {
        return ApiResponse::success([
            'otp_length' => (int) config('otp.length', 4),
            'otp_resend_after_seconds' => (int) config('otp.resend_after_seconds', 30),
            'otp_expires_in_seconds' => (int) config('otp.expires_in_seconds', 300),
            'mpin_length' => MpinRules::length(),
            'reset_session_ttl_seconds' => (int) config('otp.registration_session_ttl', 1800),
        ]);
    }

    public function sendOtp(Request $request, OtpService $otp): JsonResponse
    {
        $data = $request->validate([
            'phone' => PhoneRules::rules(),
        ], PhoneRules::messages());

        $phone = PhoneRules::normalize($data['phone']);
        $result = $otp->sendForgotMpinOtp($phone);
        $message = $result['message'] ?? '';
        unset($result['message']);

        return ApiResponse::success(array_merge($result, [
            'mpin_length' => MpinRules::length(),
        ]), $message);
    }

    public function resendOtp(Request $request, OtpService $otp): JsonResponse
    {
        return $this->sendOtp($request, $otp);
    }

    public function verifyOtp(
        Request $request,
        OtpService $otp,
        MpinResetSessionService $sessions,
    ): JsonResponse {
        $otpLength = (int) config('otp.length', 4);
        $mpinLength = MpinRules::length();

        $data = $request->validate([
            'phone' => PhoneRules::rules(),
            'otp' => ['required', 'string', "digits:{$otpLength}", 'regex:/^\d+$/'],
            'mpin' => ['nullable', 'string', "digits:{$mpinLength}", 'regex:/^\d+$/'],
            'mpin_confirmation' => ['nullable', 'same:mpin'],
        ], array_merge(PhoneRules::messages(), MpinRules::validationMessages(), [
            'otp.digits' => "OTP must be exactly {$otpLength} digits.",
            'otp.regex' => 'OTP must contain only numbers.',
        ]));

        $phone = PhoneRules::normalize($data['phone']);

        $otp->verifyForgotMpinOtp($phone, $data['otp']);

        if (filled($data['mpin'] ?? null)) {
            return $this->resetMpinForPhone($phone, $data['mpin'], $otp, null);
        }

        $session = $sessions->create($phone);

        return ApiResponse::success([
            'requires_mpin' => true,
            'reset_token' => $session['token'],
            'expires_in_seconds' => $session['expires_in'],
            'phone' => $phone,
            'mpin_length' => $mpinLength,
        ], 'OTP verified successfully. Please create your new M-PIN.');
    }

    public function setMpin(
        Request $request,
        OtpService $otp,
        MpinResetSessionService $sessions,
    ): JsonResponse {
        $mpinLength = MpinRules::length();

        $data = $request->validate([
            'reset_token' => ['required', 'string', 'size:64'],
            'mpin' => ['required', 'string', "digits:{$mpinLength}", 'regex:/^\d+$/'],
            'mpin_confirmation' => ['nullable', 'same:mpin'],
        ], MpinRules::validationMessages());

        $session = $sessions->get($data['reset_token']);

        return $this->resetMpinForPhone(
            $session['phone'],
            $data['mpin'],
            $otp,
            $data['reset_token'],
            $sessions,
        );
    }

    protected function resetMpinForPhone(
        string $phone,
        string $mpin,
        OtpService $otp,
        ?string $resetToken,
        ?MpinResetSessionService $sessions = null,
    ): JsonResponse {
        if (! $otp->phoneWasVerified($phone, 'forgot-mpin')) {
            throw ValidationException::withMessages([
                'phone' => ['Please verify OTP before setting a new M-PIN.'],
            ]);
        }

        $user = User::query()->where('phone', $phone)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'phone' => ['This mobile number is not registered.'],
            ]);
        }

        if ($user->is_blocked) {
            return ApiResponse::error('Your account has been blocked.', [], 403);
        }

        if (filled($user->mpin) && $user->verifyMpin($mpin)) {
            throw ValidationException::withMessages([
                'mpin' => ['New M-PIN must be different from your current M-PIN.'],
            ]);
        }

        $user->update(['mpin' => $mpin]);

        if (filled($resetToken) && $sessions) {
            $sessions->forget($resetToken, $phone);
        } else {
            $otp->clearVerification($phone, 'forgot-mpin');
        }

        return ApiResponse::success([
            'phone' => $phone,
            'mpin' => $mpin,
            'mpin_length' => MpinRules::length(),
            'user' => $this->userPayload($user->fresh()),
        ], 'M-PIN created successfully.');
    }
}
