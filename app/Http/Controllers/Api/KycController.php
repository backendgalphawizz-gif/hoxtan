<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\KycService;
use App\Support\ApiResponse;
use App\Support\FilamentFormFields;
use App\Support\KycPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KycController extends Controller
{
    public function config(): JsonResponse
    {
        $provider = config('kyc.provider', 'stub');
        $steps = array_values(config('kyc.steps', []));

        if ($provider === 'surepass') {
            $steps = array_map(function (array $step): array {
                if (($step['key'] ?? null) === 'pan') {
                    $step['description'] = 'Verify your PAN with Surepass (no OTP required).';
                    $step['provider_label'] = 'Surepass';
                    $step['otp_required'] = false;
                }

                return $step;
            }, $steps);
        }

        return ApiResponse::success([
            'title' => config('kyc.title', 'Identity Vault'),
            'steps' => $steps,
            'face_requirements' => config('kyc.face_requirements', []),
            'provider' => $provider,
            'third_party_enabled' => $provider !== 'stub',
            'pan_otp_required' => $provider !== 'surepass',
            'user_kyc_statuses' => config('kyc.user_kyc_statuses', []),
        ]);
    }

    public function show(Request $request, KycService $kyc): JsonResponse
    {
        $user = $request->user();
        $detail = $kyc->getOrCreateDetail($user);

        return ApiResponse::success([
            'kyc' => KycPayload::overview($user, $detail),
            'details' => KycPayload::detail($detail),
        ]);
    }

    public function requestPanOtp(Request $request, KycService $kyc): JsonResponse
    {
        $data = $request->validate([
            'pan_number' => ['required', 'string', 'size:10', 'regex:'.FilamentFormFields::PAN_REGEX],
        ]);

        $result = $kyc->requestPanOtp($request->user(), $data['pan_number']);
        $message = ($result['verified'] ?? false)
            ? (string) ($result['message'] ?? 'PAN verified successfully.')
            : 'PAN OTP sent successfully.';

        return ApiResponse::success($result, $message);
    }

    public function verifyPanOtp(Request $request, KycService $kyc): JsonResponse
    {
        $otpRequired = config('kyc.provider') !== 'surepass';

        $data = $request->validate([
            'pan_number' => ['required', 'string', 'size:10', 'regex:'.FilamentFormFields::PAN_REGEX],
            'otp' => [$otpRequired ? 'required' : 'nullable', 'string', 'min:4', 'max:6'],
        ]);

        $result = $kyc->verifyPanOtp(
            $request->user(),
            $data['pan_number'],
            $data['otp'] ?? null,
        );

        return ApiResponse::success($result, $result['message']);
    }

    public function requestAadhaarOtp(Request $request, KycService $kyc): JsonResponse
    {
        $data = $request->validate([
            'aadhaar_number' => ['required', 'string', 'regex:'.FilamentFormFields::AADHAAR_REGEX],
        ]);

        return ApiResponse::success(
            $kyc->requestAadhaarOtp($request->user(), $data['aadhaar_number']),
            'Aadhaar OTP sent successfully.',
        );
    }

    public function verifyAadhaarOtp(Request $request, KycService $kyc): JsonResponse
    {
        $data = $request->validate([
            'aadhaar_number' => ['required', 'string', 'regex:'.FilamentFormFields::AADHAAR_REGEX],
            'otp' => ['required', 'string', 'min:4', 'max:6'],
        ]);

        $result = $kyc->verifyAadhaarOtp($request->user(), $data['aadhaar_number'], $data['otp']);

        return ApiResponse::success($result, $result['message']);
    }

    public function submitFace(Request $request, KycService $kyc): JsonResponse
    {
        $request->validate([
            'selfie' => ['required', 'image', 'max:5120'],
            'image' => ['nullable', 'image', 'max:5120'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ]);

        $file = $request->file('selfie')
            ?? $request->file('image')
            ?? $request->file('photo');

        if (! $file) {
            return ApiResponse::error('Please upload a selfie image.', [], 422);
        }

        $result = $kyc->submitFace($request->user(), $file);

        return ApiResponse::success($result, $result['message']);
    }

    public function submitBank(Request $request, KycService $kyc): JsonResponse
    {
        $data = $request->validate([
            'account_holder_name' => ['required', 'string', 'max:100', 'regex:'.FilamentFormFields::NAME_REGEX],
            'bank_name' => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'min:6', 'max:30', 'regex:/^\d+$/'],
            'ifsc_code' => ['required', 'string', 'size:11', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/'],
            'upi_id' => ['nullable', 'string', 'max:100'],
        ], [
            'account_holder_name.regex' => 'Account holder name may only contain letters and spaces.',
            'account_number.regex' => 'Account number must contain digits only.',
            'ifsc_code.regex' => 'Invalid IFSC code format.',
        ]);

        $data['ifsc_code'] = strtoupper($data['ifsc_code']);

        $result = $kyc->submitBank($request->user(), $data);

        return ApiResponse::success($result, $result['message']);
    }
}
