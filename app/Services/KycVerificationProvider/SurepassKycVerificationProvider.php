<?php

namespace App\Services\KycVerificationProvider;

use App\Contracts\KycVerificationProvider;
use App\Models\User;
use App\Support\KycPayload;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Surepass KYC provider — PAN + bank verification.
 * Aadhaar / face remain local stubs until those Surepass APIs are wired.
 */
class SurepassKycVerificationProvider implements KycVerificationProvider
{
    public function sendPanOtp(string $panNumber, User $user): array
    {
        $result = $this->verifyPanComprehensive($panNumber, $user);

        return [
            'provider_reference' => $result['client_id'] ?? ('SUREPASS-PAN-'.Str::upper(Str::random(8))),
            'message' => $result['message'] ?? 'PAN verified with Surepass.',
            'verified' => true,
            'otp_required' => false,
            'data' => $result['data'] ?? [],
        ];
    }

    public function verifyPanOtp(string $panNumber, string $otp, User $user): bool
    {
        // Surepass PAN is verified at request time; OTP step is a no-op success.
        return true;
    }

    public function sendAadhaarOtp(string $aadhaarNumber, User $user): array
    {
        return [
            'provider_reference' => 'SUREPASS-AAD-PENDING-'.Str::upper(Str::random(8)),
            'message' => 'Aadhaar OTP is not yet connected to Surepass. Using local OTP flow.',
            'verified' => false,
            'otp_required' => true,
        ];
    }

    public function verifyAadhaarOtp(string $aadhaarNumber, string $otp, User $user): bool
    {
        return true;
    }

    public function submitFaceVerification(string $imagePath, User $user): array
    {
        return [
            'status' => 'submitted',
            'provider_reference' => 'SUREPASS-FACE-PENDING-'.Str::upper(Str::random(8)),
            'message' => 'Face scan submitted. Surepass face API will be connected later.',
        ];
    }

    public function submitBankVerification(array $bankDetails, User $user): array
    {
        $result = $this->verifyBankAccount($bankDetails, $user);

        return [
            'status' => 'verified',
            'provider_reference' => $result['client_id'] ?? ('SUREPASS-BANK-'.Str::upper(Str::random(8))),
            'message' => $result['message'] ?? 'Bank account verified successfully.',
            'verified' => true,
            'data' => $result['data'] ?? [],
        ];
    }

    /**
     * @return array{client_id: ?string, message: string, data: array<string, mixed>}
     */
    protected function verifyPanComprehensive(string $panNumber, User $user): array
    {
        $baseUrl = rtrim((string) config('kyc.surepass.base_url'), '/');
        $path = '/'.ltrim((string) config('kyc.surepass.pan_path', '/api/v1/pan/pan-comprehensive'), '/');
        $token = (string) config('kyc.surepass.token');
        $idField = (string) config('kyc.surepass.pan_id_field', 'id_number');
        $timeout = (int) config('kyc.surepass.timeout', 30);

        if ($baseUrl === '' || $token === '') {
            throw ValidationException::withMessages([
                'pan_number' => ['Surepass KYC is not configured. Please contact support.'],
            ]);
        }

        try {
            $response = Http::baseUrl($baseUrl)
                ->withToken($token)
                ->acceptJson()
                ->asJson()
                ->timeout($timeout)
                ->post($path, [
                    $idField => $panNumber,
                ]);
        } catch (\Throwable $e) {
            Log::error('Surepass PAN request failed.', [
                'user_id' => $user->id,
                'pan' => KycPayload::maskPan($panNumber),
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'pan_number' => ['Unable to reach PAN verification service. Please try again.'],
            ]);
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            $payload = [];
        }

        Log::info('Surepass PAN response.', [
            'user_id' => $user->id,
            'pan' => KycPayload::maskPan($panNumber),
            'http_status' => $response->status(),
            'success' => $payload['success'] ?? null,
            'status_code' => $payload['status_code'] ?? null,
            'message_code' => $payload['message_code'] ?? null,
        ]);

        if (! $response->successful() || ! ($payload['success'] ?? false)) {
            throw ValidationException::withMessages([
                'pan_number' => [$this->extractErrorMessage($payload, $response->status(), 'PAN')],
            ]);
        }

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        return [
            'client_id' => isset($data['client_id']) ? (string) $data['client_id'] : null,
            'message' => filled($payload['message'] ?? null)
                ? (string) $payload['message']
                : 'PAN verified successfully.',
            'data' => $data,
        ];
    }

    /**
     * @param  array<string, mixed>  $bankDetails
     * @return array{client_id: ?string, message: string, data: array<string, mixed>}
     */
    protected function verifyBankAccount(array $bankDetails, User $user): array
    {
        $baseUrl = rtrim((string) config('kyc.surepass.base_url'), '/');
        $configuredPath = (string) config('kyc.surepass.bank_path', '/api/v1/bank-verification/');
        $path = '/'.ltrim($configuredPath, '/');
        // Surepass bank path often requires a trailing slash.
        if (! str_ends_with($path, '/')) {
            $path .= '/';
        }

        $token = (string) config('kyc.surepass.token');
        $accountField = (string) config('kyc.surepass.bank_account_field', 'id_number');
        $ifscField = (string) config('kyc.surepass.bank_ifsc_field', 'ifsc');
        $ifscDetails = (bool) config('kyc.surepass.bank_ifsc_details', true);
        $timeout = (int) config('kyc.surepass.timeout', 30);

        $accountNumber = (string) ($bankDetails['account_number'] ?? '');
        $ifsc = strtoupper((string) ($bankDetails['ifsc_code'] ?? ''));

        if ($baseUrl === '' || $token === '') {
            throw ValidationException::withMessages([
                'account_number' => ['Surepass KYC is not configured. Please contact support.'],
            ]);
        }

        try {
            $response = Http::baseUrl($baseUrl)
                ->withToken($token)
                ->acceptJson()
                ->asJson()
                ->timeout($timeout)
                ->post($path, [
                    $accountField => $accountNumber,
                    $ifscField => $ifsc,
                    'ifsc_details' => $ifscDetails,
                ]);
        } catch (\Throwable $e) {
            Log::error('Surepass bank verification request failed.', [
                'user_id' => $user->id,
                'account' => KycPayload::maskAccount($accountNumber),
                'ifsc' => $ifsc,
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'account_number' => ['Unable to reach bank verification service. Please try again.'],
            ]);
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            $payload = [];
        }

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        Log::info('Surepass bank verification response.', [
            'user_id' => $user->id,
            'account' => KycPayload::maskAccount($accountNumber),
            'ifsc' => $ifsc,
            'http_status' => $response->status(),
            'success' => $payload['success'] ?? null,
            'status_code' => $payload['status_code'] ?? null,
            'account_exists' => $data['account_exists'] ?? null,
        ]);

        if (! $response->successful() || ! ($payload['success'] ?? false)) {
            throw ValidationException::withMessages([
                'account_number' => [$this->extractErrorMessage($payload, $response->status(), 'bank')],
            ]);
        }

        if (($data['account_exists'] ?? null) === false) {
            $remarks = filled($data['remarks'] ?? null) && is_string($data['remarks'])
                ? (string) $data['remarks']
                : 'Bank account could not be verified. Please check account number and IFSC.';

            throw ValidationException::withMessages([
                'account_number' => [$remarks],
            ]);
        }

        return [
            'client_id' => isset($data['client_id']) ? (string) $data['client_id'] : null,
            'message' => filled($payload['message'] ?? null)
                ? (string) $payload['message']
                : 'Bank account verified successfully.',
            'data' => $data,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function extractErrorMessage(array $payload, int $httpStatus, string $context = 'PAN'): string
    {
        foreach (['message', 'error', 'detail'] as $key) {
            if (filled($payload[$key] ?? null) && is_string($payload[$key])) {
                return $payload[$key];
            }
        }

        if (is_array($payload['message'] ?? null)) {
            $flat = collect($payload['message'])->flatten()->filter()->first();
            if (is_string($flat) && filled($flat)) {
                return $flat;
            }
        }

        $label = $context === 'bank' ? 'bank account' : 'PAN';

        return match (true) {
            $httpStatus === 401, $httpStatus === 403 => ucfirst($label).' verification authorization failed. Please contact support.',
            $httpStatus === 404 => ucfirst($label).' could not be verified.',
            $httpStatus === 422 => 'Invalid '.$label.' details. Please check and try again.',
            $httpStatus >= 500 => ucfirst($label).' verification service is temporarily unavailable.',
            default => ucfirst($label).' verification failed. Please check the details and try again.',
        };
    }
}
