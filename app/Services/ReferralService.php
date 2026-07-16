<?php

namespace App\Services;

use App\Models\Investment;
use App\Models\JewelleryOrder;
use App\Models\JewelleryOrderEmiInstallment;
use App\Models\Referral;
use App\Models\SigInstallment;
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

    public function purchaseThreshold(): float
    {
        return $this->settings->getFloat('referral_purchase_threshold', 2000);
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

    /**
     * Record referral on signup. Bonus is credited later when referee spend crosses the threshold.
     */
    public function processReferral(User $referee, ?User $referrer): ?Referral
    {
        if (! $referrer || $referrer->id === $referee->id) {
            return null;
        }

        if (Referral::query()->where('referee_id', $referee->id)->exists()) {
            return Referral::query()->where('referee_id', $referee->id)->first();
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

        return Referral::create([
            'referrer_id' => $referrer->id,
            'referee_id' => $referee->id,
            'referral_code_used' => (string) $referrer->referral_code,
            'bonus_amount' => $this->bonusAmount(),
            'status' => 'pending',
        ]);
    }

    /**
     * Credit pending referral bonus once referee cumulative purchases reach the threshold.
     */
    public function evaluatePendingBonus(User $referee): ?Referral
    {
        $run = function () use ($referee): ?Referral {
            if (! $this->isEnabled()) {
                return null;
            }

            $referral = Referral::query()
                ->where('referee_id', $referee->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if (! $referral) {
                return null;
            }

            $spend = $this->refereePurchaseTotal($referee);
            $threshold = $this->purchaseThreshold();

            if ($spend < $threshold) {
                return $referral;
            }

            $referrer = User::query()
                ->whereKey($referral->referrer_id)
                ->lockForUpdate()
                ->first();

            if (! $referrer || $referrer->is_blocked) {
                $referral->update([
                    'status' => 'cancelled',
                    'bonus_amount' => 0,
                ]);

                return $referral->fresh();
            }

            if ($referrer->restriction?->referral_blocked) {
                $referral->update([
                    'status' => 'cancelled',
                    'bonus_amount' => 0,
                ]);

                return $referral->fresh();
            }

            $amount = $this->bonusAmount();

            $referral->update([
                'bonus_amount' => $amount,
                'status' => 'credited',
                'credited_at' => now(),
            ]);

            $this->wallet->credit(
                $referrer,
                $amount,
                'referral_bonus',
                'Referral bonus for inviting '.$referee->name.' ('.$referee->phone.') after ₹'
                    .number_format($threshold, 2).' spend',
            );

            return $referral->fresh();
        };

        if (DB::transactionLevel() > 0) {
            return $run();
        }

        return DB::transaction($run);
    }

    /**
     * Safe to call after purchase commits (runs after current DB transaction if open).
     */
    public function evaluatePendingBonusAfterCommit(User $referee): void
    {
        $userId = (int) $referee->id;

        DB::afterCommit(function () use ($userId): void {
            $user = User::query()->find($userId);

            if ($user) {
                app(self::class)->evaluatePendingBonus($user);
            }
        });
    }

    /**
     * Cumulative purchase spend for a referred user (metal buys, jewellery, SIG).
     */
    public function refereePurchaseTotal(User $referee): float
    {
        $metal = (float) Investment::query()
            ->where('user_id', $referee->id)
            ->where('type', 'buy')
            ->where('status', 'completed')
            ->sum('total_amount');

        $jewelleryFull = (float) JewelleryOrder::query()
            ->where('user_id', $referee->id)
            ->whereNull('jewellery_emi_plan_id')
            ->whereHas('payment', fn ($q) => $q->where('status', 'completed'))
            ->whereNotIn('status', ['cancelled', 'failed', 'cart'])
            ->sum('total_amount');

        $jewelleryEmi = (float) JewelleryOrderEmiInstallment::query()
            ->where('status', 'paid')
            ->whereHas('order', fn ($q) => $q
                ->where('user_id', $referee->id)
                ->whereNotIn('status', ['cancelled', 'failed', 'cart']))
            ->sum('amount');

        $sig = (float) SigInstallment::query()
            ->where('user_id', $referee->id)
            ->where('status', 'success')
            ->sum('amount');

        return round($metal + $jewelleryFull + $jewelleryEmi + $sig, 2);
    }

    public static function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        } while (User::query()->where('referral_code', $code)->exists());

        return $code;
    }
}
