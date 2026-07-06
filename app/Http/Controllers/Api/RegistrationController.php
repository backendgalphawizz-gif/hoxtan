<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\OtpService;
use App\Services\ReferralService;
use App\Services\RegistrationSessionService;
use App\Services\UserRegistrationService;
use App\Support\ApiResponse;
use App\Support\MpinRules;
use App\Support\PhoneRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RegistrationController extends AuthController
{
    public function config(): JsonResponse
    {
        return ApiResponse::success([
            'otp_length' => (int) config('otp.length', 4),
            'otp_resend_after_seconds' => (int) config('otp.resend_after_seconds', 30),
            'otp_expires_in_seconds' => (int) config('otp.expires_in_seconds', 300),
            'mpin_length' => MpinRules::length(),
            'registration_session_ttl_seconds' => (int) config('otp.registration_session_ttl', 1800),
        ]);
    }

    public function sendOtp(Request $request, OtpService $otp): JsonResponse
    {
        $data = $request->validate([
            'phone' => PhoneRules::rules(),
        ], PhoneRules::messages());

        $result = $otp->sendRegistrationOtp(PhoneRules::normalize($data['phone']));
        $message = $result['message'] ?? '';
        unset($result['message']);

        return ApiResponse::success($result, $message);
    }

    public function resendOtp(Request $request, OtpService $otp): JsonResponse
    {
        return $this->sendOtp($request, $otp);
    }

    public function verifyOtp(
        Request $request,
        OtpService $otp,
        RegistrationSessionService $sessions,
    ): JsonResponse {
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

        $otp->verifyRegistrationOtp($phone, $data['otp']);

        $existingUser = User::query()->where('phone', $phone)->first();

        if ($existingUser) {
            if ($existingUser->is_blocked) {
                return ApiResponse::error('Your account has been blocked.', [], 403);
            }

            if (blank($data['mpin'] ?? null)) {
                $otp->markPhoneVerified($phone, 'login');

                $readableMpin = $existingUser->readableMpin();

                if (filled($readableMpin)) {
                    $token = $existingUser->createToken('mobile-app')->plainTextToken;

                    return ApiResponse::success([
                        'already_registered' => true,
                        'requires_mpin' => false,
                        'phone' => $phone,
                        'mpin' => $readableMpin,
                        'mpin_length' => $mpinLength,
                        'has_mpin' => true,
                        'mpin_legacy_hashed' => false,
                        'token' => $token,
                        'user' => $this->userPayload($existingUser),
                    ], 'Login successful.');
                }

                return ApiResponse::success([
                    'already_registered' => true,
                    'requires_mpin' => true,
                    'phone' => $phone,
                    'mpin' => null,
                    'mpin_length' => $mpinLength,
                    'has_mpin' => filled($existingUser->getRawOriginal('mpin')),
                    'mpin_legacy_hashed' => $existingUser->usesLegacyHashedMpin(),
                    'next_api' => '/api/v1/register/login-mpin',
                    'user' => $this->userPayload($existingUser),
                ], $existingUser->usesLegacyHashedMpin()
                    ? 'OTP verified. Please reset your M-PIN once using Forgot M-PIN or admin.'
                    : 'OTP verified. Please enter your M-PIN to login.');
            }

            if (! $existingUser->verifyMpin($data['mpin'])) {
                throw ValidationException::withMessages([
                    'mpin' => ['Invalid M-PIN. Please try again.'],
                ]);
            }

            $token = $existingUser->createToken('mobile-app')->plainTextToken;

            return ApiResponse::success([
                'already_registered' => true,
                'phone' => $phone,
                'mpin' => $existingUser->readableMpin() ?? $data['mpin'],
                'mpin_length' => $mpinLength,
                'token' => $token,
                'user' => $this->userPayload($existingUser),
            ], 'Login successful.');
        }

        $session = $sessions->create($phone);

        return ApiResponse::success([
            'already_registered' => false,
            'registration_token' => $session['token'],
            'token' => $session['token'],
            'expires_in_seconds' => $session['expires_in'],
            'phone' => $phone,
            'mpin_length' => $mpinLength,
        ], 'Mobile number verified successfully.');
    }

    public function loginMpin(Request $request, OtpService $otp): JsonResponse
    {
        $mpinLength = MpinRules::length();

        $data = $request->validate([
            'phone' => PhoneRules::rules(),
            'mpin' => ['required', 'string', "digits:{$mpinLength}", 'regex:/^\d+$/'],
        ], array_merge(PhoneRules::messages(), MpinRules::validationMessages()));

        $phone = PhoneRules::normalize($data['phone']);

        if (! $otp->phoneWasVerified($phone, 'login')) {
            throw ValidationException::withMessages([
                'phone' => ['Please verify OTP before entering your M-PIN.'],
            ]);
        }

        $user = User::query()->where('phone', $phone)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'phone' => ['This mobile number is not registered.'],
            ]);
        }

        return $this->loginWithMpin($user, $phone, $data['mpin'], $otp);
    }

    public function details(Request $request, RegistrationSessionService $sessions, ReferralService $referrals): JsonResponse
    {
        $data = $request->validate([
            'registration_token' => ['required', 'string', 'size:64'],
            'name' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z\s]+$/'],
            'referral_code' => ['nullable', 'string', 'max:12'],
        ], [
            'name.regex' => 'Full name may only contain letters and spaces.',
        ]);

        if (filled($data['referral_code'] ?? null) && ! $referrals->findReferrerByCode($data['referral_code'])) {
            throw ValidationException::withMessages([
                'referral_code' => ['Invalid referral code.'],
            ]);
        }

        $session = $sessions->updateProfile(
            $data['registration_token'],
            $data['name'],
            $data['referral_code'] ?? null,
        );

        return ApiResponse::success([
            'phone' => $session['phone'],
            'name' => $session['name'],
            'referral_code' => $session['referral_code'],
        ], 'Profile details saved.');
    }

    public function validateReferral(Request $request, ReferralService $referrals): JsonResponse
    {
        $code = trim((string) ($request->input('referral_code') ?? $request->query('referral_code') ?? ''));

        if ($code === '') {
            return ApiResponse::error('Referral code is required.', ['valid' => false], 200);
        }

        if (strlen($code) > 12) {
            return ApiResponse::error('Referral code must not exceed 12 characters.', ['valid' => false], 200);
        }

        $referrer = $referrals->findReferrerByCode($code);

        if (! $referrer) {
            return ApiResponse::error('Invalid referral code.', ['valid' => false], 200);
        }

        return ApiResponse::success([
            'valid' => true,
            'referral_code' => $referrer->referral_code,
            'referrer_name' => $referrer->name,
        ], 'Referral code applied successfully.');
    }

    public function setMpin(
        Request $request,
        RegistrationSessionService $sessions,
        UserRegistrationService $registration,
    ): JsonResponse {
        $length = MpinRules::length();

        $data = $request->validate([
            'registration_token' => ['required', 'string', 'size:64'],
            'mpin' => ['required', 'string', "digits:{$length}", 'regex:/^\d+$/'],
        ], MpinRules::validationMessages());

        $session = $sessions->get($data['registration_token']);

        if (blank($session['name'] ?? null)) {
            throw ValidationException::withMessages([
                'registration_token' => ['Please complete your profile details before setting MPIN.'],
            ]);
        }

        $user = $registration->register(
            $session['name'],
            $session['phone'],
            $data['mpin'],
            $session['referral_code'] ?? null,
        );

        $sessions->forget($data['registration_token'], $session['phone']);

        $token = $user->createToken('mobile-app')->plainTextToken;

        return ApiResponse::success([
            'mpin' => $data['mpin'],
            'mpin_length' => $length,
            'token' => $token,
            'user' => $this->userPayload($user),
        ], 'M-PIN created successfully.', 201);
    }
}
