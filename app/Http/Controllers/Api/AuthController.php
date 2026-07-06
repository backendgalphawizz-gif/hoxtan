<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserRegistrationService;
use App\Support\ApiResponse;
use App\Support\MpinRules;
use App\Support\PhoneRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function register(Request $request, UserRegistrationService $registration): JsonResponse
    {
        $data = $request->validate(
            array_merge([
                'name' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z\s]+$/'],
                'phone' => ['required', 'string', 'regex:/^\d{10}$/'],
                'referral_code' => ['nullable', 'string', 'max:12'],
            ], MpinRules::validationRules()),
            MpinRules::validationMessages(),
        );

        $user = $registration->register(
            $data['name'],
            $data['phone'],
            $data['mpin'],
            $data['referral_code'] ?? null,
        );

        $token = $user->createToken('mobile-app')->plainTextToken;

        return ApiResponse::success([
            'mpin' => $data['mpin'],
            'mpin_length' => MpinRules::length(),
            'token' => $token,
            'user' => $this->userPayload($user),
        ], 'Registration successful.', 201);
    }

    public function login(Request $request): JsonResponse
    {
        $length = MpinRules::length();

        $data = $request->validate([
            'phone' => PhoneRules::rules(),
            'mpin' => ['required', 'string', "digits:{$length}", 'regex:/^\d+$/'],
        ], array_merge(PhoneRules::messages(), MpinRules::validationMessages()));

        $phone = PhoneRules::normalize($data['phone']);

        $user = User::query()
            ->where('phone', $phone)
            ->first();

        if (! $user || ! $user->verifyMpin($data['mpin'])) {
            return ApiResponse::error('Invalid mobile number or MPIN.', [], 401);
        }

        if ($user->is_blocked) {
            return ApiResponse::error('Your account has been blocked.', [], 403);
        }

        $token = $user->createToken('mobile-app')->plainTextToken;

        return ApiResponse::success([
            'phone' => $phone,
            'mpin' => $data['mpin'],
            'mpin_length' => $length,
            'token' => $token,
            'user' => $this->userPayload($user),
        ], 'Login successful.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return ApiResponse::success([], 'Logged out successfully.');
    }

    protected function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'referral_code' => $user->referral_code,
            'wallet_balance' => (float) $user->wallet_balance,
            'gold_holdings' => (float) $user->gold_holdings,
            'silver_holdings' => (float) $user->silver_holdings,
            'nominee' => [
                'name' => $user->nominee_name,
                'relation' => $user->nominee_relation,
                'phone' => $user->nominee_phone,
                'date_of_birth' => $user->nominee_date_of_birth?->toDateString(),
            ],
        ];
    }

    protected function loginWithMpin(User $user, string $phone, string $mpin, \App\Services\OtpService $otp): JsonResponse
    {
        if ($user->is_blocked) {
            return ApiResponse::error('Your account has been blocked.', [], 403);
        }

        if (! $user->verifyMpin($mpin)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'mpin' => ['Invalid M-PIN. Please try again.'],
            ]);
        }

        $otp->clearVerification($phone, 'login');

        $token = $user->createToken('mobile-app')->plainTextToken;

        return ApiResponse::success([
            'phone' => $phone,
            'mpin' => $mpin,
            'mpin_length' => MpinRules::length(),
            'token' => $token,
            'user' => $this->userPayload($user),
        ], 'Login successful.');
    }
}
