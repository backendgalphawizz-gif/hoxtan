<?php

namespace App\Support;

use App\Models\User;

class UserProfilePayload
{
    public static function make(User $user): array
    {
        $user->loadMissing(['referredBy:id,name,phone', 'kycDetail']);

        $photoUrl = self::profilePhotoUrl($user->profile_photo);
        $dateOfBirth = $user->date_of_birth?->toDateString();

        return [
            'id' => (int) $user->id,
            'name' => (string) ($user->name ?? ''),
            'phone' => (string) ($user->phone ?? ''),
            'phone_verified' => (bool) $user->is_verified,
            // Never null — Flutter models often cast this as non-nullable String/num.
            'mpin' => $user->readableMpin() ?? '',
            'mpin_length' => (int) MpinRules::length(),
            'has_mpin' => filled($user->getRawOriginal('mpin')),
            'mpin_legacy_hashed' => $user->usesLegacyHashedMpin(),
            'email' => self::displayEmail($user->email),
            'primary_residence' => $user->primary_residence,
            'gender' => $user->gender,
            'date_of_birth' => $dateOfBirth,
            'dob' => $dateOfBirth,
            'date_of_birth_display' => $user->date_of_birth?->format('d/m/Y'),
            'image' => $photoUrl,
            'image_url' => $photoUrl,
            'profile_photo_url' => $photoUrl,
            'market_alerts' => (bool) $user->market_alerts,
            'referral_code' => $user->referral_code,
            // Always JSON numbers (never null) for Flutter `as num` casts.
            'wallet_balance' => self::money($user->wallet_balance),
            'gold_holdings' => self::grams($user->gold_holdings),
            'silver_holdings' => self::grams($user->silver_holdings),
            'kyc_status' => $user->kyc_status ?? 'pending',
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
            'aadhaar' => self::aadhaar($user),
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
            'verification_status' => $detail?->pan_verification_status ?: 'action_required',
            'verified' => $detail?->pan_verification_status === 'verified',
            'verified_at' => $detail?->pan_verified_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function aadhaar(User $user): array
    {
        $detail = $user->kycDetail;

        return [
            'aadhaar_number' => KycPayload::maskAadhaar($detail?->aadhaar_number),
            'aadhaar_number_masked' => KycPayload::maskAadhaar($detail?->aadhaar_number),
            'full_name' => $detail?->full_name,
            'dob' => $detail?->date_of_birth?->toDateString(),
            'dob_display' => $detail?->date_of_birth?->format('d/m/Y'),
            'digilocker_client_id' => $detail?->digilocker_client_id,
            'verification_status' => $detail?->aadhaar_verification_status ?: 'action_required',
            'verified' => $detail?->aadhaar_verification_status === 'verified',
            'verified_at' => $detail?->aadhaar_verified_at?->toIso8601String(),
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
            'verification_status' => $detail?->bank_verification_status ?: 'action_required',
            'verified' => in_array($detail?->bank_verification_status, ['verified', 'approved'], true),
            'submitted_at' => $detail?->bank_submitted_at?->toIso8601String(),
        ];
    }

    protected static function money(mixed $value): float
    {
        return round((float) ($value ?? 0), 2);
    }

    protected static function grams(mixed $value): float
    {
        return round((float) ($value ?? 0), 4);
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
