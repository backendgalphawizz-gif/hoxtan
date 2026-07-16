<?php

namespace App\Support;

use App\Models\User;
use App\Support\AssetUrl;

class UserProfilePayload
{
    public static function make(User $user): array
    {
        $user->loadMissing(['referredBy:id,name,phone', 'kycDetail']);

        $photoUrl = self::profilePhotoUrl($user->profile_photo);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'phone_verified' => (bool) $user->is_verified,
            'mpin' => $user->readableMpin(),
            'mpin_length' => MpinRules::length(),
            'has_mpin' => filled($user->getRawOriginal('mpin')),
            'mpin_legacy_hashed' => $user->usesLegacyHashedMpin(),
            'email' => self::displayEmail($user->email),
            'primary_residence' => $user->primary_residence,
            'gender' => $user->gender,
            'date_of_birth' => $user->date_of_birth?->toDateString(),
            'date_of_birth_display' => $user->date_of_birth?->format('d/m/Y'),
            'image' => $photoUrl,
            'image_url' => $photoUrl,
            'profile_photo_url' => $photoUrl,
            'market_alerts' => (bool) $user->market_alerts,
            'referral_code' => $user->referral_code,
            'wallet_balance' => (float) $user->wallet_balance,
            'gold_holdings' => (float) $user->gold_holdings,
            'silver_holdings' => (float) $user->silver_holdings,
            'kyc_status' => $user->kyc_status,
            'kyc_completed_at' => $user->kycDetail?->reviewed_at?->toDateString(),
            'referred_by' => $user->referredBy ? [
                'name' => $user->referredBy->name,
                'phone' => $user->referredBy->phone,
            ] : null,
            'nominee' => [
                'name' => $user->nominee_name,
                'relation' => $user->nominee_relation,
                'phone' => $user->nominee_phone,
                'date_of_birth' => $user->nominee_date_of_birth?->toDateString(),
                'date_of_birth_display' => $user->nominee_date_of_birth?->format('d/m/Y'),
            ],
            'pan' => self::pan($user),
            'bank' => self::bank($user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function pan(User $user): array
    {
        $detail = $user->kycDetail;

        return [
            'full_name' => $detail?->full_name,
            'pan_number' => KycPayload::maskPan($detail?->pan_number),
            'pan_number_masked' => KycPayload::maskPan($detail?->pan_number),
            'dob' => $detail?->date_of_birth?->toDateString(),
            'dob_display' => $detail?->date_of_birth?->format('d/m/Y'),
            'verification_status' => $detail?->pan_verification_status,
            'verified' => $detail?->pan_verification_status === 'verified',
            'verified_at' => $detail?->pan_verified_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function bank(User $user): array
    {
        $detail = $user->kycDetail;

        return [
            'account_holder_name' => $detail?->account_holder_name,
            'bank_name' => $detail?->bank_name,
            'account_number' => $detail?->account_number,
            'account_number_masked' => KycPayload::maskAccount($detail?->account_number),
            'ifsc_code' => $detail?->ifsc_code,
            'upi_id' => $detail?->upi_id,
            'verification_status' => $detail?->bank_verification_status,
            'verified' => in_array($detail?->bank_verification_status, ['verified', 'approved'], true),
            'submitted_at' => $detail?->bank_submitted_at?->toIso8601String(),
        ];
    }

    protected static function displayEmail(?string $email): ?string
    {
        if (blank($email)) {
            return null;
        }

        if (str_ends_with($email, '@hoxtan.app')) {
            return null;
        }

        return $email;
    }

    protected static function profilePhotoUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        return AssetUrl::publicStorage($path);
    }
}
