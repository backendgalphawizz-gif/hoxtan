<?php

namespace App\Services\KycVerificationProvider;

use App\Contracts\KycVerificationProvider;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Local stub until a third-party KYC provider is integrated.
 */
class StubKycVerificationProvider implements KycVerificationProvider
{
    public function sendPanOtp(string $panNumber, User $user): array
    {
        return [
            'provider_reference' => 'STUB-PAN-'.Str::upper(Str::random(8)),
            'message' => 'PAN OTP request accepted. Third-party verification will be connected later.',
        ];
    }

    public function verifyPanOtp(string $panNumber, string $otp, User $user): bool
    {
        return true;
    }

    public function sendAadhaarOtp(string $aadhaarNumber, User $user): array
    {
        return [
            'provider_reference' => 'STUB-AAD-'.Str::upper(Str::random(8)),
            'message' => 'Aadhaar OTP request accepted. UIDAI integration will be connected later.',
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
            'provider_reference' => 'STUB-FACE-'.Str::upper(Str::random(8)),
            'message' => 'Face scan submitted. Biometric provider will be connected later.',
        ];
    }

    public function submitBankVerification(array $bankDetails, User $user): array
    {
        return [
            'status' => 'pending',
            'provider_reference' => 'STUB-BANK-'.Str::upper(Str::random(8)),
            'message' => 'Bank details submitted. Penny-drop verification will be connected later.',
        ];
    }
}
