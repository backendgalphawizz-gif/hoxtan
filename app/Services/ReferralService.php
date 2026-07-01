<?php

namespace App\Services;

use App\Models\Referral;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReferralService
{
    public function __construct(
        protected AppSettingService $settings,
        protected WalletService $wallet,
    ) {}

    public function isEnabled(): bool
    {
        return $this->settings->getBool('referral_bonus_enabled', true);
    }

    public function bonusAmount(): float
    {
        return $this->settings->getFloat('referral_bonus_amount', 100);
    }

    public function findReferrerByCode(?string $code): ?User
    {
        if (blank($code)) {
            return null;
        }

        return User::query()
            ->where('referral_code', strtoupper(trim($code)))
            ->where('is_blocked', false)
            ->first();
    }

    public function processReferral(User $referee, ?User $referrer): ?Referral
    {
        if (! $referrer || $referrer->id === $referee->id) {
            return null;
        }

        if (! $this->isEnabled()) {
            return Referral::create([
                'referrer_id' => $referrer->id,
                'referee_id' => $referee->id,
                'referral_code_used' => (string) $referrer->referral_code,
                'bonus_amount' => 0,
                'status' => 'cancelled',
            ]);
        }

        return DB::transaction(function () use ($referee, $referrer): Referral {
            $amount = $this->bonusAmount();

            $referral = Referral::create([
                'referrer_id' => $referrer->id,
                'referee_id' => $referee->id,
                'referral_code_used' => (string) $referrer->referral_code,
                'bonus_amount' => $amount,
                'status' => 'credited',
                'credited_at' => now(),
            ]);

            $this->wallet->credit(
                $referrer,
                $amount,
                'referral_bonus',
                'Referral bonus for inviting '.$referee->name.' ('.$referee->phone.')',
            );

            return $referral;
        });
    }

    public static function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        } while (User::query()->where('referral_code', $code)->exists());

        return $code;
    }
}
