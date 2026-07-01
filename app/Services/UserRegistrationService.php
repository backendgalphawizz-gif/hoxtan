<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserRegistrationService
{
    public function __construct(
        protected AppSettingService $settings,
        protected ReferralService $referrals,
        protected WalletService $wallet,
    ) {}

    public function register(string $name, string $phone, string $mpin, ?string $referralCode = null): User
    {
        $phone = preg_replace('/\D/', '', $phone) ?? $phone;

        if (User::query()->where('phone', $phone)->exists()) {
            throw ValidationException::withMessages([
                'phone' => ['This mobile number is already registered.'],
            ]);
        }

        $referrer = $this->referrals->findReferrerByCode($referralCode);

        if (filled($referralCode) && ! $referrer) {
            throw ValidationException::withMessages([
                'referral_code' => ['Invalid referral code.'],
            ]);
        }

        return DB::transaction(function () use ($name, $phone, $mpin, $referrer): User {
            $user = User::create([
                'name' => $name,
                'phone' => $phone,
                'email' => $phone.'@hoxtan.app',
                'password' => Hash::make(Str::random(40)),
                'mpin' => $mpin,
                'referral_code' => ReferralService::generateUniqueCode(),
                'referred_by_id' => $referrer?->id,
                'role' => 'user',
                'kyc_status' => 'pending',
                'is_verified' => true,
            ]);

            $this->applyWelcomeBonus($user);

            if ($referrer) {
                $this->referrals->processReferral($user, $referrer);
            }

            return $user->fresh();
        });
    }

    public function applyWelcomeBonus(User $user): void
    {
        if (! $this->settings->getBool('welcome_bonus_enabled', true)) {
            return;
        }

        $amount = $this->settings->getFloat('welcome_bonus_amount', 50);

        if ($amount <= 0) {
            return;
        }

        $this->wallet->credit(
            $user,
            $amount,
            'welcome_bonus',
            'Welcome bonus on registration',
        );
    }
}
