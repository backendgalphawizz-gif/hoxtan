<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserRegistrationService;
use App\Support\MpinRules;
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

        return response()->json([
            'message' => 'Registration successful.',
            'token' => $token,
            'user' => $this->userPayload($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $length = MpinRules::length();

        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^\d{10}$/'],
            'mpin' => ['required', 'string', "digits:{$length}", 'regex:/^\d+$/'],
        ], MpinRules::validationMessages());

        $user = \App\Models\User::query()
            ->where('phone', $data['phone'])
            ->first();

        if (! $user || ! $user->verifyMpin($data['mpin'])) {
            return response()->json(['message' => 'Invalid mobile number or MPIN.'], 401);
        }

        if ($user->is_blocked) {
            return response()->json(['message' => 'Your account has been blocked.'], 403);
        }

        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'user' => $this->userPayload($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    protected function userPayload(\App\Models\User $user): array
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
}
