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
 * Surepass KYC provider — PAN, bank, and DigiLocker Aadhaar verification.
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
            'provider_reference' => 'SUREPASS-AAD-DIGILOCKER',
            'message' => 'Use DigiLocker flow via /api/v1/digilocker/initialize for Aadhaar verification.',
            'verified' => false,
            'otp_required' => false,
            'digilocker_required' => true,
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
     * @param  array<string, mixed>  $options
     * @return array{client_id: ?string, message: string, data: array<string, mixed>}
     */
    public function initializeDigilocker(User $user, array $options = []): array
    {
        $payload = [
            'data' => array_merge($this->digilockerDefaults(), $options),
        ];

        $response = $this->requestSurepass(
            'post',
            $this->digilockerPath('digilocker_initialize_path', '/api/v1/digilocker/initialize'),
            $payload,
            $user,
            'digilocker',
        );

        $data = is_array($response['data'] ?? null) ? $response['data'] : [];

        return [
            'client_id' => isset($data['client_id']) ? (string) $data['client_id'] : null,
            'message' => (string) ($response['message'] ?? 'DigiLocker initialized successfully.'),
            'data' => $data,
        ];
    }

    /**
     * @return array{message: string, data: array<string, mixed>}
     */
    public function getDigilockerStatus(string $clientId, User $user): array
    {
        $path = rtrim($this->digilockerPath('digilocker_status_path', '/api/v1/digilocker/status'), '/')
            .'/'.urlencode($clientId);

        $response = $this->requestSurepass('get', $path, [], $user, 'digilocker');

        return [
            'message' => (string) ($response['message'] ?? 'DigiLocker status fetched.'),
            'data' => is_array($response['data'] ?? null) ? $response['data'] : [],
        ];
    }

    /**
     * @return array{message: string, data: array<string, mixed>}
     */
    public function downloadDigilockerAadhaar(string $clientId, User $user): array
    {
        $path = rtrim($this->digilockerPath('digilocker_download_aadhaar_path', '/api/v1/digilocker/download-aadhaar'), '/')
            .'/'.urlencode($clientId);

        $response = $this->requestSurepass('post', $path, [], $user, 'digilocker');

        return [
            'message' => (string) ($response['message'] ?? 'Aadhaar downloaded successfully.'),
            'data' => is_array($response['data'] ?? null) ? $response['data'] : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function extractAadhaarNumber(array $data): ?string
    {
        foreach (['aadhaar_number', 'uid', 'aadhaar_uid', 'id_number', 'masked_aadhaar'] as $key) {
            $digits = preg_replace('/\D/', '', (string) ($data[$key] ?? '')) ?? '';

            if (strlen($digits) === 12) {
                return $digits;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function digilockerDefaults(): array
    {
        $defaults = (array) config('kyc.surepass.digilocker', []);

        return array_filter([
            'signup_flow' => $defaults['signup_flow'] ?? true,
            'auth_type' => $defaults['auth_type'] ?? 'app',
            'logo_url' => $defaults['logo_url'] ?? null,
            'voice_assistant_lang' => $defaults['voice_assistant_lang'] ?? 'hi',
            'voice_assistant' => $defaults['voice_assistant'] ?? true,
            'retry_count' => $defaults['retry_count'] ?? 3,
            'skip_main_screen' => $defaults['skip_main_screen'] ?? false,
        ], fn ($value) => $value !== null);
    }

    protected function digilockerPath(string $configKey, string $fallback): string
    {
        $path = (string) config('kyc.surepass.'.$configKey, $fallback);

        return '/'.ltrim($path, '/');
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function requestSurepass(string $method, string $path, array $body, User $user, string $context): array
    {
        $baseUrl = rtrim((string) config('kyc.surepass.base_url'), '/');
        $token = (string) config('kyc.surepass.token');
        $timeout = (int) config('kyc.surepass.timeout', 30);

        if ($baseUrl === '' || $token === '') {
            throw ValidationException::withMessages([
                $context === 'digilocker' ? 'digilocker' : 'kyc' => ['Surepass KYC is not configured. Please contact support.'],
            ]);
        }

        try {
            $request = Http::baseUrl($baseUrl)
                ->withToken($token)
                ->acceptJson()
                ->timeout($timeout);

            $response = match (strtolower($method)) {
                'get' => $request->get($path),
                default => $request->asJson()->post($path, $body),
            };
        } catch (\Throwable $e) {
            Log::error('Surepass request failed.', [
                'user_id' => $user->id,
                'context' => $context,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                $context === 'digilocker' ? 'digilocker' : 'kyc' => ['Unable to reach verification service. Please try again.'],
            ]);
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            $payload = [];
        }

        Log::info('Surepass response.', [
            'user_id' => $user->id,
            'context' => $context,
            'path' => $path,
            'http_status' => $response->status(),
            'success' => $payload['success'] ?? null,
        ]);

        if (! $response->successful() || ! ($payload['success'] ?? false)) {
            $field = $context === 'digilocker' ? 'digilocker' : ($context === 'bank' ? 'account_number' : 'pan_number');

            throw ValidationException::withMessages([
                $field => [$this->extractErrorMessage($payload, $response->status(), $context)],
            ]);
        }

        return $payload;
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

        $label = match ($context) {
            'bank' => 'bank account',
            'digilocker', 'aadhaar' => 'Aadhaar DigiLocker',
            default => 'PAN',
        };

        return match (true) {
            $httpStatus === 401, $httpStatus === 403 => ucfirst($label).' verification authorization failed. Please contact support.',
            $httpStatus === 404 => ucfirst($label).' could not be verified.',
            $httpStatus === 422 => 'Invalid '.$label.' details. Please check and try again.',
            $httpStatus >= 500 => ucfirst($label).' verification service is temporarily unavailable.',
            default => ucfirst($label).' verification failed. Please check the details and try again.',
        };
    }
}
