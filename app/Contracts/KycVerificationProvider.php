<?php

namespace App\Contracts;

use App\Models\User;

interface KycVerificationProvider
{
    /**
     * @return array{provider_reference: ?string, message: string}
     */
    public function sendPanOtp(string $panNumber, User $user): array;

    public function verifyPanOtp(string $panNumber, string $otp, User $user): bool;

    /**
     * @return array{provider_reference: ?string, message: string}
     */
    public function sendAadhaarOtp(string $aadhaarNumber, User $user): array;

    public function verifyAadhaarOtp(string $aadhaarNumber, string $otp, User $user): bool;

    /**
     * @return array{status: string, provider_reference: ?string, message: string}
     */
    public function submitFaceVerification(string $imagePath, User $user): array;

    /**
     * @return array{status: string, provider_reference: ?string, message: string}
     */
    public function submitBankVerification(array $bankDetails, User $user): array;
}
