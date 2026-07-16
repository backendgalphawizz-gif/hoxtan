<?php

namespace App\Services;

use App\Contracts\KycVerificationProvider;
use App\Models\KycDetail;
use App\Models\User;
use App\Services\KycVerificationProvider\SurepassKycVerificationProvider;
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
        $dob = $data['dob'] ?? $data['date_of_birth'] ?? null;

        // Surepass (and similar) return identity fields — require match with profile before verifying.
        if (filled($fullName) || filled($dob)) {
            $this->assertPanIdentityMatchesUser(
                $user,
                is_string($fullName) ? $fullName : null,
                is_string($dob) ? $dob : null,
            );
        }

        $updates = [
            'pan_number' => $panNumber,
            'pan_verification_status' => 'verified',
            'pan_verified_at' => now(),
        ];

        if (filled($fullName) && is_string($fullName)) {
            $updates['full_name'] = $fullName;
        }

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
                'dob' => $dob,
            ],
            'name_matched' => true,
            'dob_matched' => true,
            'kyc' => KycPayload::overview($user->fresh(), $detail->fresh()),
        ];
    }

    /**
     * Match Surepass PAN full_name + dob against the user's profile name and date of birth.
     */
    protected function assertPanIdentityMatchesUser(User $user, ?string $providerFullName, ?string $providerDob): void
    {
        $errors = [];

        if (filled($providerFullName)) {
            $userName = trim((string) $user->name);

            if ($userName === '') {
                $errors['pan_number'][] = 'Please update your full name in your profile before verifying PAN.';
            } elseif ($this->normalizePersonName($userName) !== $this->normalizePersonName($providerFullName)) {
                $errors['pan_number'][] = 'PAN name does not match your registered full name.';
            }
        }

        if (filled($providerDob)) {
            $userDob = $user->date_of_birth?->toDateString();

            if (blank($userDob)) {
                $errors['pan_number'][] = 'Please update your date of birth in your profile before verifying PAN.';
            } else {
                try {
                    $providerDobNormalized = \Illuminate\Support\Carbon::parse($providerDob)->toDateString();
                } catch (\Throwable) {
                    throw ValidationException::withMessages([
                        'pan_number' => ['PAN date of birth could not be verified. Please try again.'],
                    ]);
                }

                if ($userDob !== $providerDobNormalized) {
                    $errors['pan_number'][] = 'PAN date of birth does not match your registered date of birth.';
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    protected function normalizePersonName(string $name): string
    {
        $normalized = preg_replace('/\s+/u', ' ', strtoupper(trim($name)));

        return is_string($normalized) ? $normalized : '';
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function initializeDigilocker(User $user, array $options = []): array
    {
        $this->assertKycEditable($user);

        $provider = $this->surepassProvider();
        $result = $provider->initializeDigilocker($user, $options);
        $data = is_array($result['data'] ?? null) ? $result['data'] : [];
        $clientId = (string) ($data['client_id'] ?? $result['client_id'] ?? '');

        if ($clientId === '') {
            throw ValidationException::withMessages([
                'digilocker' => ['DigiLocker client_id was not returned by Surepass.'],
            ]);
        }

        $detail = $this->getOrCreateDetail($user);
        $detail->update([
            'digilocker_client_id' => $clientId,
            'aadhaar_verification_status' => 'submitted',
        ]);

        return [
            'client_id' => $clientId,
            'token' => $data['token'] ?? null,
            'url' => $data['url'] ?? null,
            'expiry_seconds' => $data['expiry_seconds'] ?? null,
            'digilocker' => $data,
            'kyc' => KycPayload::overview($user->fresh(), $detail->fresh()),
        ];
    }

    /**
     * Flow:
     * 1) Call Surepass GET /api/v1/digilocker/status/{client_id}
     * 2) Use that DigiLocker response
     * 3) If completed=true → download Aadhaar + mark verified (ahead logic)
     * 4) If completed=false → return DigiLocker status as-is for mobile to keep polling
     *
     * @return array<string, mixed>
     */
    public function checkDigilockerStatus(User $user, string $clientId): array
    {
        $this->assertKycEditable($user);
        $clientId = trim($clientId);

        $detail = $this->getOrCreateDetail($user);

        if (filled($detail->digilocker_client_id) && $detail->digilocker_client_id !== $clientId) {
            throw ValidationException::withMessages([
                'client_id' => ['This DigiLocker session does not belong to your account.'],
            ]);
        }

        if ($detail->aadhaar_verification_status === 'verified'
            && $detail->digilocker_client_id === $clientId) {
            return [
                'verified' => true,
                'completed' => true,
                'failed' => false,
                'client_id' => $clientId,
                'aadhaar_number_masked' => KycPayload::maskAadhaar($detail->aadhaar_number),
                'message' => 'Aadhaar already verified.',
                'kyc' => KycPayload::overview($user, $detail),
            ];
        }

        // Step 1: DigiLocker status API (Surepass).
        $provider = $this->surepassProvider();
        $statusResult = $provider->getDigilockerStatus($clientId, $user);
        $statusData = is_array($statusResult['data'] ?? null) ? $statusResult['data'] : [];
        $completed = ($statusData['completed'] ?? false) === true
            || ($statusData['status'] ?? null) === 'completed';
        $failed = ($statusData['failed'] ?? false) === true;

        // Step 2: Act on DigiLocker response.
        if ($failed) {
            $detail->update([
                'digilocker_client_id' => $clientId,
                'aadhaar_verification_status' => 'rejected',
            ]);

            throw ValidationException::withMessages([
                'digilocker' => [
                    (string) ($statusData['error_description'] ?? 'DigiLocker verification failed. Please try again.'),
                ],
            ]);
        }

        if (! $completed) {
            if (blank($detail->digilocker_client_id)) {
                $detail->update(['digilocker_client_id' => $clientId]);
            }

            // Return DigiLocker response as-is — mobile keeps polling.
            return [
                'verified' => false,
                'completed' => false,
                'failed' => false,
                'client_id' => $clientId,
                'status' => $statusData['status'] ?? null,
                'aadhaar_linked' => $statusData['aadhaar_linked'] ?? null,
                'error_count' => $statusData['error_count'] ?? 0,
                'message' => (string) ($statusResult['message'] ?? 'DigiLocker verification in progress.'),
                'digilocker' => $statusData,
                'kyc' => KycPayload::overview($user->fresh(), $detail->fresh()),
            ];
        }

        // Step 3: DigiLocker completed → ahead code (download Aadhaar + save + verify).
        $result = $this->markAadhaarVerifiedFromDigilocker($user, $clientId, $provider);
        $result['digilocker_status'] = $statusData;

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    protected function markAadhaarVerifiedFromDigilocker(
        User $user,
        string $clientId,
        SurepassKycVerificationProvider $provider,
    ): array {
        $download = $provider->downloadDigilockerAadhaar($clientId, $user);
        $downloadData = is_array($download['data'] ?? null) ? $download['data'] : [];
        $identity = $provider->extractDigilockerIdentity($downloadData);
        $aadhaarNumber = $provider->extractAadhaarNumber($downloadData);

        $detail = $this->getOrCreateDetail($user);
        $updates = [
            'digilocker_client_id' => $clientId,
            'aadhaar_verification_status' => 'verified',
            'aadhaar_verified_at' => now(),
        ];

        if (filled($aadhaarNumber)) {
            $this->assertAadhaarFormat($aadhaarNumber);
            $updates['aadhaar_number'] = $aadhaarNumber;
        }

        if (filled($identity['name'])) {
            $updates['full_name'] = $identity['name'];
        }

        if (filled($identity['date_of_birth'])) {
            try {
                $updates['date_of_birth'] = \Illuminate\Support\Carbon::parse($identity['date_of_birth'])->toDateString();
            } catch (\Throwable) {
                // Ignore unparseable DOB from provider.
            }
        }

        if (is_array($identity['address'] ?? null)) {
            $address = collect($identity['address'])
                ->filter(fn ($value) => filled($value))
                ->implode(', ');
            if (filled($address)) {
                $updates['address'] = $address;
            }
            if (filled($identity['address']['pincode'] ?? null)) {
                $updates['pincode'] = (string) $identity['address']['pincode'];
            }
            if (filled($identity['address']['state'] ?? null)) {
                $updates['state'] = (string) $identity['address']['state'];
            }
            if (filled($identity['address']['district'] ?? null)) {
                $updates['city'] = (string) $identity['address']['district'];
            } elseif (filled($identity['address']['vtc'] ?? null)) {
                $updates['city'] = (string) $identity['address']['vtc'];
            }
        }

        $detail->update($updates);
        $this->syncUserKycStatus($user->fresh(), $detail->fresh());

        return [
            'verified' => true,
            'completed' => true,
            'failed' => false,
            'client_id' => $clientId,
            'aadhaar_number_masked' => KycPayload::maskAadhaar($aadhaarNumber),
            'message' => 'Aadhaar verified successfully via DigiLocker.',
            'aadhaar' => [
                'full_name' => $identity['name'],
                'dob' => $identity['date_of_birth'],
                'gender' => $identity['gender'],
                'address' => $identity['address'],
            ],
            'digilocker' => [
                'client_id' => $downloadData['client_id'] ?? $clientId,
                'metadata' => $downloadData['digilocker_metadata'] ?? null,
            ],
            'kyc' => KycPayload::overview($user->fresh(), $detail->fresh()),
        ];
    }

    protected function surepassProvider(): SurepassKycVerificationProvider
    {
        if (! $this->provider instanceof SurepassKycVerificationProvider) {
            throw ValidationException::withMessages([
                'digilocker' => ['DigiLocker Aadhaar verification requires Surepass provider.'],
            ]);
        }

        return $this->provider;
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

        $data['ifsc_code'] = strtoupper((string) $data['ifsc_code']);
        $data['account_number'] = preg_replace('/\s+/', '', (string) $data['account_number']) ?? '';

        $providerResponse = $this->provider->submitBankVerification($data, $user);
        $providerData = is_array($providerResponse['data'] ?? null) ? $providerResponse['data'] : [];
        $status = (string) ($providerResponse['status'] ?? 'pending');
        $providerFullName = isset($providerData['full_name']) && is_string($providerData['full_name'])
            ? $providerData['full_name']
            : null;

        if ($status === 'verified' && filled($providerFullName)) {
            $this->assertBankAccountHolderNameMatches(
                (string) $data['account_holder_name'],
                $providerFullName,
            );
        }

        $ifscDetails = is_array($providerData['ifsc_details'] ?? null) ? $providerData['ifsc_details'] : [];
        $bankName = $data['bank_name']
            ?? ($ifscDetails['bank'] ?? null)
            ?? ($ifscDetails['BANK'] ?? null);

        $detail = $this->getOrCreateDetail($user);
        $detail->update([
            'account_holder_name' => filled($providerFullName)
                ? $providerFullName
                : $data['account_holder_name'],
            'bank_name' => $bankName,
            'account_number' => $data['account_number'],
            'ifsc_code' => $data['ifsc_code'],
            'upi_id' => $data['upi_id'] ?? ($providerData['upi_id'] ?? null),
            'bank_verification_status' => $status,
            'bank_submitted_at' => now(),
        ]);

        $detail = $detail->fresh();
        $this->syncUserKycStatus($user->fresh(), $detail);

        $verified = $status === 'verified';

        return [
            'message' => $verified
                ? (string) ($providerResponse['message'] ?? 'Bank account verified successfully.')
                : 'Bank details submitted successfully.',
            'verified' => $verified,
            'name_matched' => $verified && filled($providerFullName),
            'provider_reference' => $providerResponse['provider_reference'] ?? null,
            'provider_message' => $providerResponse['message'] ?? null,
            'bank' => [
                'account_holder_name' => $detail->account_holder_name,
                'bank_name' => $detail->bank_name,
                'account_number_masked' => KycPayload::maskAccount($detail->account_number),
                'ifsc_code' => $detail->ifsc_code,
                'upi_id' => $detail->upi_id,
                'verification_status' => $detail->bank_verification_status,
                'account_exists' => $providerData['account_exists'] ?? null,
                'remarks' => $providerData['remarks'] ?? null,
                'ifsc_details' => $ifscDetails !== [] ? $ifscDetails : null,
            ],
            'kyc' => KycPayload::overview($user->fresh(), $detail),
        ];
    }

    /**
     * Match Surepass bank full_name against the submitted account_holder_name only.
     */
    protected function assertBankAccountHolderNameMatches(string $accountHolderName, string $providerFullName): void
    {
        if ($this->normalizePersonName($accountHolderName) !== $this->normalizePersonName($providerFullName)) {
            throw ValidationException::withMessages([
                'account_holder_name' => ['Account holder name does not match the name registered with the bank.'],
            ]);
        }
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

        if ($this->autoApproveSurepassKycIfEligible($user, $detail)) {
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

    /**
     * When Surepass has verified PAN, Aadhaar, and bank, mark KYC approved without admin action.
     */
    public function autoApproveSurepassKycIfEligible(User $user, KycDetail $detail): bool
    {
        if (! KycPayload::isSurepassPanBankVerified($detail)) {
            return false;
        }

        $detailUpdates = [
            'reviewed_at' => now(),
            'face_verification_notes' => 'Auto-approved: PAN, Aadhaar, and bank verified via Surepass.',
        ];

        if (blank($detail->submitted_at)) {
            $detailUpdates['submitted_at'] = now();
        }

        if (filled($detail->selfie_photo) && $detail->face_verification_status !== 'approved') {
            $detailUpdates['face_verification_status'] = 'approved';
        }

        $detail->update($detailUpdates);
        $user->update(['kyc_status' => 'approved']);

        return true;
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
