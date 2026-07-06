<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

class UserProfilePayload
{
    public static function make(User $user): array
    {
        $user->loadMissing(['referredBy:id,name,phone', 'kycDetail']);

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
            'profile_photo_url' => self::profilePhotoUrl($user->profile_photo),
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
        if (blank($path) || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return asset('storage/'.$path);
    }
}
