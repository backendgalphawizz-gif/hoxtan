<?php

namespace App\Services;

use App\Contracts\KycVerificationProvider;
use App\Models\KycDetail;
use App\Models\User;
use App\Support\FilamentFormFields;
use App\Support\KycPayload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class KycService
{
    public function __construct(
        protected KycVerificationProvider $provider,
    ) {}

    public function getOrCreateDetail(User $user): KycDetail
    {
        return $user->kycDetail()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'full_name' => $user->name,
                'pan_verification_status' => 'action_required',
                'aadhaar_verification_status' => 'action_required',
                'face_verification_status' => 'pending',
                'bank_verification_status' => 'pending',
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function requestPanOtp(User $user, string $panNumber): array
    {
        $this->assertKycEditable($user);
        $panNumber = strtoupper($panNumber);
        $this->assertPanFormat($panNumber);

        $providerResponse = $this->provider->sendPanOtp($panNumber, $user);

        // Surepass (and similar) verify PAN directly — no OTP step.
        if (($providerResponse['verified'] ?? false) === true) {
            return $this->markPanVerified($user, $panNumber, $providerResponse);
        }

        $otpPayload = $this->storeStepOtp($user, 'pan', $panNumber);

        $detail = $this->getOrCreateDetail($user);
        $detail->update([
            'pan_number' => $panNumber,
            'pan_verification_status' => 'otp_sent',
        ]);

        return array_merge($otpPayload, [
            'otp_required' => true,
            'verified' => false,
            'pan_number_masked' => KycPayload::maskPan($panNumber),
            'provider_reference' => $providerResponse['provider_reference'] ?? null,
            'provider_message' => $providerResponse['message'] ?? null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyPanOtp(User $user, string $panNumber, ?string $otp = null): array
    {
        $this->assertKycEditable($user);
        $panNumber = strtoupper($panNumber);
        $this->assertPanFormat($panNumber);

        $detail = $this->getOrCreateDetail($user);

        if ($detail->pan_verification_status === 'verified'
            && strtoupper((string) $detail->pan_number) === $panNumber) {
            return [
                'message' => 'PAN already verified.',
                'otp_required' => false,
                'verified' => true,
                'kyc' => KycPayload::overview($user->fresh(), $detail->fresh()),
            ];
        }

        // Direct provider verification path (Surepass): OTP optional.
        if (config('kyc.provider') === 'surepass') {
            $providerResponse = $this->provider->sendPanOtp($panNumber, $user);

            if (($providerResponse['verified'] ?? false) !== true) {
                throw ValidationException::withMessages([
                    'pan_number' => ['PAN verification failed. Please try again.'],
                ]);
            }

            return $this->markPanVerified($user, $panNumber, $providerResponse);
        }

        if (blank($otp)) {
            throw ValidationException::withMessages([
                'otp' => ['OTP is required.'],
            ]);
        }

        $this->verifyStepOtp($user, 'pan', $panNumber, $otp);

        if (! $this->provider->verifyPanOtp($panNumber, $otp, $user)) {
            throw ValidationException::withMessages([
                'otp' => ['PAN verification failed. Please try again.'],
            ]);
        }

        return $this->markPanVerified($user, $panNumber, [
            'message' => 'PAN verified successfully.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $providerResponse
     * @return array<string, mixed>
     */
    protected function markPanVerified(User $user, string $panNumber, array $providerResponse): array
    {
        $detail = $this->getOrCreateDetail($user);
        $data = is_array($providerResponse['data'] ?? null) ? $providerResponse['data'] : [];
        $fullName = $data['full_name'] ?? $data['name'] ?? null;

        $updates = [
            'pan_number' => $panNumber,
            'pan_verification_status' => 'verified',
            'pan_verified_at' => now(),
        ];

        if (filled($fullName) && is_string($fullName)) {
            $updates['full_name'] = $fullName;
        }

        $dob = $data['dob'] ?? $data['date_of_birth'] ?? null;
        if (filled($dob) && is_string($dob)) {
            try {
                $updates['date_of_birth'] = \Illuminate\Support\Carbon::parse($dob)->toDateString();
            } catch (\Throwable) {
                // Ignore unparseable DOB from provider.
            }
        }

        $detail->update($updates);
        $this->syncUserKycStatus($user->fresh(), $detail->fresh());

        return [
            'message' => (string) ($providerResponse['message'] ?? 'PAN verified successfully.'),
            'otp_required' => false,
            'verified' => true,
            'pan_number_masked' => KycPayload::maskPan($panNumber),
            'provider_reference' => $providerResponse['provider_reference'] ?? null,
            'provider_message' => $providerResponse['message'] ?? null,
            'pan' => [
                'full_name' => $fullName,
                'aadhaar_linked' => $data['aadhaar_linked'] ?? null,
                'category' => $data['category'] ?? null,
                'dob' => $data['dob'] ?? $data['date_of_birth'] ?? null,
            ],
            'kyc' => KycPayload::overview($user->fresh(), $detail->fresh()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function requestAadhaarOtp(User $user, string $aadhaarNumber): array
    {
        $this->assertKycEditable($user);
        $aadhaarNumber = preg_replace('/\D/', '', $aadhaarNumber) ?? '';
        $this->assertAadhaarFormat($aadhaarNumber);

        $providerResponse = $this->provider->sendAadhaarOtp($aadhaarNumber, $user);
        $otpPayload = $this->storeStepOtp($user, 'aadhaar', $aadhaarNumber);

        $detail = $this->getOrCreateDetail($user);
        $detail->update([
            'aadhaar_number' => $aadhaarNumber,
            'aadhaar_verification_status' => 'otp_sent',
        ]);

        return array_merge($otpPayload, [
            'aadhaar_number_masked' => KycPayload::maskAadhaar($aadhaarNumber),
            'provider_reference' => $providerResponse['provider_reference'] ?? null,
            'provider_message' => $providerResponse['message'] ?? null,
            'uidai_secured' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyAadhaarOtp(User $user, string $aadhaarNumber, string $otp): array
    {
        $this->assertKycEditable($user);
        $aadhaarNumber = preg_replace('/\D/', '', $aadhaarNumber) ?? '';
        $this->verifyStepOtp($user, 'aadhaar', $aadhaarNumber, $otp);

        if (! $this->provider->verifyAadhaarOtp($aadhaarNumber, $otp, $user)) {
            throw ValidationException::withMessages([
                'otp' => ['Aadhaar verification failed. Please try again.'],
            ]);
        }

        $detail = $this->getOrCreateDetail($user);
        $detail->update([
            'aadhaar_number' => $aadhaarNumber,
            'aadhaar_verification_status' => 'verified',
            'aadhaar_verified_at' => now(),
        ]);

        $this->syncUserKycStatus($user->fresh(), $detail->fresh());

        return [
            'message' => 'Aadhaar verified successfully.',
            'kyc' => KycPayload::overview($user->fresh(), $detail->fresh()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function submitFace(User $user, UploadedFile $photo): array
    {
        $this->assertKycEditable($user);

        $detail = $this->getOrCreateDetail($user);

        if (filled($detail->selfie_photo)) {
            Storage::disk('public')->delete($detail->selfie_photo);
        }

        $path = $photo->store('kyc/selfie/'.$user->id, 'public');
        $providerResponse = $this->provider->submitFaceVerification($path, $user);

        $detail->update([
            'selfie_photo' => $path,
            'face_verification_status' => 'pending',
            'face_submitted_at' => now(),
            'face_verification_notes' => $providerResponse['message'] ?? null,
        ]);

        $this->syncUserKycStatus($user->fresh(), $detail->fresh());

        return [
            'message' => 'Face scan submitted successfully.',
            'face_photo_url' => \App\Support\AssetUrl::publicStorage($path),
            'provider_reference' => $providerResponse['provider_reference'] ?? null,
            'provider_message' => $providerResponse['message'] ?? null,
            'kyc' => KycPayload::overview($user->fresh(), $detail->fresh()),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function submitBank(User $user, array $data): array
    {
        $this->assertKycEditable($user);

        $detail = $this->getOrCreateDetail($user);
        $providerResponse = $this->provider->submitBankVerification($data, $user);

        $detail->update([
            'account_holder_name' => $data['account_holder_name'],
            'bank_name' => $data['bank_name'],
            'account_number' => $data['account_number'],
            'ifsc_code' => strtoupper($data['ifsc_code']),
            'upi_id' => $data['upi_id'] ?? null,
            'bank_verification_status' => $providerResponse['status'] ?? 'pending',
            'bank_submitted_at' => now(),
        ]);

        $this->syncUserKycStatus($user->fresh(), $detail->fresh());

        return [
            'message' => 'Bank details submitted successfully.',
            'provider_reference' => $providerResponse['provider_reference'] ?? null,
            'provider_message' => $providerResponse['message'] ?? null,
            'kyc' => KycPayload::overview($user->fresh(), $detail->fresh()),
        ];
    }

    /**
     * Admin / system path: verify PAN via active provider without user KYC edit locks.
     *
     * @return array<string, mixed>
     */
    public function applyPanVerification(User $user, string $panNumber): array
    {
        $panNumber = strtoupper(trim($panNumber));
        $this->assertPanFormat($panNumber);

        $providerResponse = $this->provider->sendPanOtp($panNumber, $user);

        if (($providerResponse['verified'] ?? false) !== true) {
            throw ValidationException::withMessages([
                'pan_number' => [(string) ($providerResponse['message'] ?? 'PAN verification failed.')],
            ]);
        }

        return $this->markPanVerified($user, $panNumber, $providerResponse);
    }

    public function syncUserKycStatus(User $user, KycDetail $detail): void
    {
        if ($user->kyc_status === 'approved') {
            return;
        }

        if (KycPayload::canSubmit($detail, $user)) {
            $user->update([
                'kyc_status' => 'submitted',
            ]);

            if (blank($detail->submitted_at)) {
                $detail->update(['submitted_at' => now()]);
            }

            return;
        }

        if (in_array($user->kyc_status, ['rejected'], true)) {
            return;
        }

        $user->update(['kyc_status' => 'pending']);
    }

    protected function assertKycEditable(User $user): void
    {
        if ($user->kyc_status === 'approved') {
            throw ValidationException::withMessages([
                'kyc' => ['Your KYC is already completed.'],
            ]);
        }

        if (in_array($user->kyc_status, ['submitted', 'under_review'], true)) {
            throw ValidationException::withMessages([
                'kyc' => ['Your KYC is under review and cannot be changed right now.'],
            ]);
        }
    }

    protected function assertPanFormat(string $panNumber): void
    {
        if (! preg_match(FilamentFormFields::PAN_REGEX, $panNumber)) {
            throw ValidationException::withMessages([
                'pan_number' => ['Invalid PAN format (e.g. ABCDE1234F).'],
            ]);
        }
    }

    protected function assertAadhaarFormat(string $aadhaarNumber): void
    {
        if (! preg_match(FilamentFormFields::AADHAAR_REGEX, $aadhaarNumber)) {
            throw ValidationException::withMessages([
                'aadhaar_number' => ['Aadhaar must be exactly 12 digits.'],
            ]);
        }
    }

    /**
     * @return array{message: string, resend_after_seconds: int, expires_in_seconds: int, otp?: string}
     */
    protected function storeStepOtp(User $user, string $step, string $identifier): array
    {
        $resendAfter = config('otp.resend_after_seconds', 30);
        $resendKey = $this->otpResendCacheKey($user->id, $step);

        if (Cache::has($resendKey)) {
            $retryAfter = max(1, (int) Cache::get($resendKey) - now()->timestamp);

            throw ValidationException::withMessages([
                'otp' => ["Please wait {$retryAfter} seconds before requesting a new OTP."],
            ])->status(429);
        }

        $code = $this->generateOtpCode();
        $expiresIn = config('otp.expires_in_seconds', 300);

        Cache::put($this->otpCacheKey($user->id, $step), [
            'code' => $code,
            'identifier' => $identifier,
            'attempts' => 0,
            'expires_at' => now()->addSeconds($expiresIn)->timestamp,
        ], $expiresIn);

        Cache::put($resendKey, now()->addSeconds($resendAfter)->timestamp, $resendAfter);

        Log::info('KYC OTP generated.', [
            'user_id' => $user->id,
            'step' => $step,
            'identifier' => $identifier,
            'otp' => $code,
        ]);

        $payload = [
            'message' => 'OTP sent successfully.',
            'resend_after_seconds' => $resendAfter,
            'expires_in_seconds' => $expiresIn,
        ];

        if (config('otp.expose_in_response')) {
            $payload['otp'] = $code;
        }

        return $payload;
    }

    protected function verifyStepOtp(User $user, string $step, string $identifier, string $otp): void
    {
        $record = Cache::get($this->otpCacheKey($user->id, $step));

        if (! is_array($record)) {
            throw ValidationException::withMessages([
                'otp' => ['OTP has expired or was not requested. Please request a new OTP.'],
            ]);
        }

        if (($record['identifier'] ?? null) !== $identifier) {
            throw ValidationException::withMessages([
                'otp' => ['The submitted details do not match the OTP request.'],
            ]);
        }

        if (($record['expires_at'] ?? 0) < now()->timestamp) {
            Cache::forget($this->otpCacheKey($user->id, $step));

            throw ValidationException::withMessages([
                'otp' => ['OTP has expired. Please request a new OTP.'],
            ]);
        }

        $maxAttempts = config('otp.max_attempts', 5);
        $attempts = (int) ($record['attempts'] ?? 0);

        if ($attempts >= $maxAttempts) {
            Cache::forget($this->otpCacheKey($user->id, $step));

            throw ValidationException::withMessages([
                'otp' => ['Too many invalid attempts. Please request a new OTP.'],
            ]);
        }

        if (! hash_equals((string) $record['code'], trim($otp))) {
            $record['attempts'] = $attempts + 1;
            $ttl = max(1, (int) $record['expires_at'] - now()->timestamp);
            Cache::put($this->otpCacheKey($user->id, $step), $record, $ttl);

            throw ValidationException::withMessages([
                'otp' => ['Invalid OTP. Please try again.'],
            ]);
        }

        Cache::forget($this->otpCacheKey($user->id, $step));
    }

    protected function generateOtpCode(): string
    {
        $length = max(4, min(6, (int) config('otp.length', 4)));
        $max = (10 ** $length) - 1;
        $min = 10 ** ($length - 1);

        return (string) random_int($min, $max);
    }

    protected function otpCacheKey(int $userId, string $step): string
    {
        return "kyc:otp:{$step}:{$userId}";
    }

    protected function otpResendCacheKey(int $userId, string $step): string
    {
        return "kyc:otp:{$step}:resend:{$userId}";
    }
}
