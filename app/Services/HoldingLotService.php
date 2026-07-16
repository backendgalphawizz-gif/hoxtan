<?php

namespace App\Services;

use App\Events\UserAssetsUpdated;
use App\Models\Investment;
use App\Models\MetalWithdrawal;
use App\Models\User;
use App\Support\KycPayload;
use App\Support\WalletHoldingsSnapshot;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HoldingLotService
{
    public function __construct(
        protected MetalPurchaseService $purchases,
        protected MetalWithdrawalService $withdrawals,
        protected MetalRateService $metalRates,
        protected UserHoldingsService $holdings,
        protected HoldingCertificateService $certificates,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(User $user, ?string $metalType = null): array
    {
        $lots = $this->lotsQuery($user, $metalType)->get();
        $bonusPercent = (float) config('holdings.hold_bonus_percent', 1);
        $holdDays = (int) config('holdings.hold_bonus_after_days', 365);
        $pendingSellLotIds = $this->pendingSellLotIdsForUser((int) $user->id);

        $serialized = $lots->map(
            fn (Investment $lot) => $this->lotPayload($lot, $bonusPercent, $holdDays, $pendingSellLotIds)
        )->values();

        $totalGrams = round((float) $serialized->sum('remaining_grams'), 4);
        $totalValue = round((float) $serialized->sum('current_value'), 2);
        $eligibleBonusValue = round((float) $serialized->where('bonus_eligible', true)->sum('bonus_amount'), 2);

        return [
            'title' => 'My Holdings',
            'hold_bonus_percent' => $bonusPercent,
            'hold_bonus_after_days' => $holdDays,
            'hold_bonus_message' => config('holdings.hold_bonus_message'),
            'summary' => [
                'total_lots' => $serialized->count(),
                'total_grams' => $totalGrams,
                'total_grams_display' => number_format($totalGrams, 4).' g',
                'current_value' => $totalValue,
                'current_value_display' => '₹'.number_format($totalValue, 2),
                'eligible_bonus_amount' => $eligibleBonusValue,
                'eligible_bonus_amount_display' => '₹'.number_format($eligibleBonusValue, 2),
            ],
            'lots' => $serialized->all(),
            'actions' => [
                'sell' => [
                    'endpoint' => '/api/v1/holdings/sell',
                    'method' => 'POST',
                    'payload_example' => [
                        'weight_grams' => 50,
                        'payment_method' => 'upi',
                        'transaction_id' => 'TXN123',
                    ],
                    'note' => 'Sell by grams only (metal_type defaults to gold). Allowed after 48 hours from purchase. Admin/sub-admin can approve, or auto-approve after 2 hours at current rate to bank.',
                ],
                'purchase' => [
                    'endpoint' => '/api/v1/holdings/purchase',
                    'method' => 'POST',
                    'payload_examples' => [
                        'default' => [
                            'weight_grams' => 5,
                            'amount' => 1000,
                            'payment_method' => 'upi',
                            'transaction_id' => 'TXN123',
                        ],
                        'with_metal_type' => [
                            'metal_type' => 'silver',
                            'weight_grams' => 50,
                            'amount' => 5000,
                            'payment_method' => 'upi',
                            'transaction_id' => 'TXN123',
                        ],
                    ],
                    'note' => 'Send weight_grams + amount calculated on mobile. metal_type defaults to gold. No input_mode needed.',
                ],
                'claim_bonus' => [
                    'endpoint' => '/api/v1/holdings/claim-bonus',
                    'method' => 'POST',
                    'note' => 'Claim 1% bonus on lots held for 1 year (uses current market value).',
                ],
            ],
        ];
    }

    /**
     * Purchase metal into holdings (creates a new hold lot).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function purchase(User $user, array $data): array
    {
        $result = $this->purchases->purchase($user, $data);
        $investment = $result['investment'];

        // Ensure lot fields even if purchase path already set them.
        if ($investment instanceof Investment && $investment->type === 'buy') {
            $investment->forceFill([
                'remaining_grams' => $investment->remaining_grams ?? $investment->quantity_grams,
                'hold_started_at' => $investment->hold_started_at ?? $investment->created_at ?? now(),
                'purpose' => $investment->purpose ?: 'hold',
            ])->save();
            $result['investment'] = $investment->fresh();
        }

        $result['holding'] = $this->summary($user->fresh(), $data['metal_type'] ?? null);

        return $result;
    }

    /**
     * Sell one holding lot by lot_id (full remaining grams of that lot).
     * Eligible only after sell_after_hours (default 48h) from purchase.
     * Creates admin withdrawal request; auto-approves after sell_auto_approve_hours (default 2h).
     * On approve, payout uses the current metal rate.
     *
     * @param  array{lot_id: int}  $data
     * @return array<string, mixed>
     */
    public function sell(User $user, array $data): array
    {
        KycPayload::assertCanPerformTransactions($user);

        $lotId = (int) ($data['lot_id'] ?? 0);
        $sellAfterHours = (int) config('holdings.sell_after_hours', 48);

        $lot = Investment::query()
            ->where('user_id', $user->id)
            ->whereKey($lotId)
            ->where('type', 'buy')
            ->where('status', 'completed')
            ->first();

        if (! $lot) {
            throw ValidationException::withMessages([
                'lot_id' => ['Holding lot not found.'],
            ]);
        }

        $metalType = (string) $lot->metal_type;
        $weightGrams = round((float) ($lot->remaining_grams ?? 0), 4);

        if ($weightGrams <= 0) {
            throw ValidationException::withMessages([
                'lot_id' => ['This holding lot has no remaining balance to sell.'],
            ]);
        }

        $started = $lot->hold_started_at ?? $lot->created_at;
        $unlockedAt = $started ? Carbon::parse($started)->addHours($sellAfterHours) : null;

        if ($unlockedAt && $unlockedAt->isFuture()) {
            $hoursLeft = max(1, (int) ceil(now()->floatDiffInHours($unlockedAt)));

            throw ValidationException::withMessages([
                'lot_id' => [
                    'You can sell this lot only after '.$sellAfterHours.' hours from purchase. '
                        .'Try again in about '.$hoursLeft.' hour(s).',
                ],
            ]);
        }

        if ($this->hasPendingSellRequest($lot->id, (int) $user->id)) {
            throw ValidationException::withMessages([
                'lot_id' => [
                    'A sell request for this lot is already pending. You cannot sell this lot again until that request is completed or rejected.',
                ],
            ]);
        }

        $sellable = $this->holdings->sellableGrams($user->id, $metalType, $sellAfterHours);
        $totalRemaining = round((float) Investment::query()
            ->where('user_id', $user->id)
            ->where('metal_type', $metalType)
            ->where('type', 'buy')
            ->where('status', 'completed')
            ->where('remaining_grams', '>', 0)
            ->sum('remaining_grams'), 4);
        $locked = max(0, round($totalRemaining - $sellable, 4));

        $payload = [
            'asset_source' => $metalType,
            'input_mode' => 'weight',
            'weight_grams' => $weightGrams,
            'from_holdings' => true,
            'source_lot_id' => $lot->id,
            'holdings_sell_after_hours' => $sellAfterHours,
            'holdings_auto_approve_hours' => (int) config('holdings.sell_auto_approve_hours', 2),
        ];

        $result = $this->withdrawals->create($user, $payload);
        $result['holding'] = $this->summary($user->fresh(), $metalType);
        $result['lot'] = $this->lotPayload($lot->fresh());
        $result['sellable_grams'] = $sellable;
        $result['locked_grams'] = $locked;
        $result['sell_after_hours'] = $sellAfterHours;
        $result['auto_approve_hours'] = (int) config('holdings.sell_auto_approve_hours', 2);

        return $result;
    }

    /**
     * Credit 1% bonus (as extra metal grams) for eligible lots.
     *
     * @return array{credited_lots: int, bonus_grams: float, lots: list<array<string, mixed>>}
     */
    public function claimBonus(User $user, ?string $metalType = null, ?int $lotId = null): array
    {
        $bonusPercent = (float) config('holdings.hold_bonus_percent', 1);
        $holdDays = (int) config('holdings.hold_bonus_after_days', 365);
        $credited = [];
        $totalBonusGrams = 0.0;

        DB::transaction(function () use ($user, $metalType, $lotId, $bonusPercent, $holdDays, &$credited, &$totalBonusGrams): void {
            $query = $this->lotsQuery($user, $metalType)->lockForUpdate();
            if ($lotId !== null) {
                $query->whereKey($lotId);
            }

            foreach ($query->get() as $lot) {
                if (! $this->isBonusEligible($lot, $holdDays)) {
                    continue;
                }

                $remaining = round((float) ($lot->remaining_grams ?? 0), 4);
                if ($remaining <= 0) {
                    continue;
                }

                $bonusGrams = round($remaining * ($bonusPercent / 100), 4);
                if ($bonusGrams <= 0) {
                    continue;
                }

                $rate = (float) $this->metalRates->getCurrentRatePerGram($lot->metal_type);
                $bonusValue = round($bonusGrams * $rate, 2);

                $bonusLot = Investment::query()->create([
                    'user_id' => $user->id,
                    'metal_type' => $lot->metal_type,
                    'type' => 'buy',
                    'quantity_grams' => $bonusGrams,
                    'remaining_grams' => $bonusGrams,
                    'rate_per_gram' => $rate,
                    'amount' => $bonusValue,
                    'gst_amount' => 0,
                    'total_amount' => $bonusValue,
                    'status' => 'completed',
                    'hold_started_at' => now(),
                    'hold_bonus_credited_at' => now(),
                    'purpose' => 'hold_bonus',
                    'notes' => 'Hold anniversary bonus '.$bonusPercent.'% for lot '.$lot->reference_id,
                ]);

                $lot->forceFill([
                    'hold_bonus_credited_at' => now(),
                ])->save();

                $this->holdings->recalculateForUser($user->id);

                $totalBonusGrams = round($totalBonusGrams + $bonusGrams, 4);
                $credited[] = [
                    'source_lot_id' => $lot->id,
                    'bonus_lot_id' => $bonusLot->id,
                    'metal_type' => $lot->metal_type,
                    'bonus_grams' => $bonusGrams,
                    'bonus_amount' => $bonusValue,
                    'bonus_amount_display' => '₹'.number_format($bonusValue, 2),
                ];
            }
        });

        if ($credited === []) {
            throw ValidationException::withMessages([
                'lot' => ['No eligible holding lot found for 1-year bonus yet.'],
            ]);
        }

        $fresh = $user->fresh();
        $wallet = WalletHoldingsSnapshot::make($fresh, $this->metalRates);
        UserAssetsUpdated::dispatchSafe((int) $user->id, $wallet['assets'], 'hold_bonus');

        return [
            'credited_lots' => count($credited),
            'bonus_grams' => $totalBonusGrams,
            'bonus_percent' => $bonusPercent,
            'lots' => $credited,
            'holding' => $this->summary($fresh, $metalType),
            'wallet' => [
                'gold_holdings' => $wallet['gold_holdings'],
                'silver_holdings' => $wallet['silver_holdings'],
            ],
        ];
    }

    /**
     * Cron helper: credit all eligible users.
     */
    public function creditAllEligibleBonuses(): int
    {
        $count = 0;
        $holdDays = (int) config('holdings.hold_bonus_after_days', 365);
        $cutoff = now()->subDays($holdDays);

        $userIds = Investment::query()
            ->where('type', 'buy')
            ->where('status', 'completed')
            ->where('purpose', '!=', 'hold_bonus')
            ->whereNull('hold_bonus_credited_at')
            ->where('remaining_grams', '>', 0)
            ->where('hold_started_at', '<=', $cutoff)
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $user = User::query()->find($userId);
            if (! $user) {
                continue;
            }

            try {
                $this->claimBonus($user);
                $count++;
            } catch (ValidationException) {
                // none eligible right now
            }
        }

        return $count;
    }

    /**
     * Reduce buy-lot remaining_grams FIFO when metal is sold.
     */
    public function consumeLots(User $user, string $metalType, float $grams): void
    {
        $this->holdings->consumeHoldLots($user->id, $metalType, $grams);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Investment>
     */
    protected function lotsQuery(User $user, ?string $metalType = null)
    {
        return Investment::query()
            ->where('user_id', $user->id)
            ->where('type', 'buy')
            ->where('status', 'completed')
            ->with('holdingCertificate')
            ->where(function ($q): void {
                $q->whereNull('purpose')->orWhere('purpose', '!=', 'hold_bonus');
            })
            ->where(function ($q): void {
                $q->where('remaining_grams', '>', 0)
                    ->orWhereNull('remaining_grams');
            })
            ->when(
                filled($metalType),
                fn ($q) => $q->where('metal_type', strtolower((string) $metalType))
            )
            ->orderByDesc('hold_started_at')
            ->orderByDesc('id');
    }

    /**
     * @param  array<int, true>|null  $pendingSellLotIds
     * @return array<string, mixed>
     */
    public function lotPayload(
        Investment $lot,
        ?float $bonusPercent = null,
        ?int $holdDays = null,
        ?array $pendingSellLotIds = null,
    ): array {
        $bonusPercent ??= (float) config('holdings.hold_bonus_percent', 1);
        $holdDays ??= (int) config('holdings.hold_bonus_after_days', 365);
        $remaining = round((float) ($lot->remaining_grams ?? $lot->quantity_grams ?? 0), 4);
        $rate = (float) $this->metalRates->getCurrentRatePerGram($lot->metal_type);
        $currentValue = round($remaining * $rate, 2);
        $started = $lot->hold_started_at ?? $lot->created_at;
        $bonusDueAt = $started ? Carbon::parse($started)->addDays($holdDays) : null;
        $eligible = $this->isBonusEligible($lot, $holdDays);
        $bonusAmount = $eligible ? round($currentValue * ($bonusPercent / 100), 2) : 0.0;
        $bonusGrams = $eligible ? round($remaining * ($bonusPercent / 100), 4) : 0.0;
        $sellAfterHours = (int) config('holdings.sell_after_hours', 48);
        $sellUnlockedAt = $started ? Carbon::parse($started)->addHours($sellAfterHours) : null;
        $sellRequestPending = $pendingSellLotIds !== null
            ? isset($pendingSellLotIds[(int) $lot->id])
            : $this->hasPendingSellRequest((int) $lot->id, (int) $lot->user_id);
        $canSell = $remaining > 0
            && ! $sellRequestPending
            && ($sellUnlockedAt === null || ! $sellUnlockedAt->isFuture());

        $certificate = $this->certificates->payload($lot->holdingCertificate);
        $certificateUrl = is_array($certificate) ? ($certificate['download_url'] ?? null) : null;

        return [
            'id' => $lot->id,
            'reference_id' => $lot->reference_id,
            'metal_type' => $lot->metal_type,
            'purchased_grams' => round((float) $lot->quantity_grams, 4),
            'remaining_grams' => $remaining,
            'remaining_grams_display' => number_format($remaining, 4).' g',
            'rate_per_gram' => round((float) $lot->rate_per_gram, 2),
            'invested_amount' => round((float) $lot->total_amount, 2),
            'invested_amount_display' => '₹'.number_format((float) $lot->total_amount, 2),
            'current_rate_per_gram' => $rate,
            'current_value' => $currentValue,
            'current_value_display' => '₹'.number_format($currentValue, 2),
            'hold_started_at' => $started?->toDateString(),
            'hold_started_at_display' => $started?->format('d/m/Y'),
            'bonus_due_at' => $bonusDueAt?->toDateString(),
            'bonus_due_at_display' => $bonusDueAt?->format('d/m/Y'),
            'days_held' => $started ? $started->diffInDays(now()) : 0,
            'days_remaining_for_bonus' => $bonusDueAt && $bonusDueAt->isFuture()
                ? now()->diffInDays($bonusDueAt)
                : 0,
            'bonus_percent' => $bonusPercent,
            'bonus_eligible' => $eligible,
            'bonus_credited' => $lot->hold_bonus_credited_at !== null,
            'bonus_credited_at' => $lot->hold_bonus_credited_at?->toIso8601String(),
            'bonus_grams' => $bonusGrams,
            'bonus_amount' => $bonusAmount,
            'bonus_amount_display' => '₹'.number_format($bonusAmount, 2),
            'can_sell' => $canSell,
            'sell_request_pending' => $sellRequestPending,
            'sell_unlocks_at' => $sellUnlockedAt?->toIso8601String(),
            'certificate' => $certificate,
            'certificate_url' => $certificateUrl,
        ];
    }

    protected function hasPendingSellRequest(int $lotId, int $userId): bool
    {
        return MetalWithdrawal::query()
            ->where('user_id', $userId)
            ->where('source_lot_id', $lotId)
            ->where('from_holdings', true)
            ->where('status', 'pending')
            ->exists();
    }

    /**
     * @return array<int, true>
     */
    protected function pendingSellLotIdsForUser(int $userId): array
    {
        $ids = MetalWithdrawal::query()
            ->where('user_id', $userId)
            ->where('from_holdings', true)
            ->where('status', 'pending')
            ->whereNotNull('source_lot_id')
            ->pluck('source_lot_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_fill_keys($ids, true);
    }

    protected function isBonusEligible(Investment $lot, int $holdDays): bool
    {
        if ($lot->hold_bonus_credited_at !== null) {
            return false;
        }

        if (($lot->purpose ?? '') === 'hold_bonus') {
            return false;
        }

        $remaining = round((float) ($lot->remaining_grams ?? 0), 4);
        if ($remaining <= 0) {
            return false;
        }

        $started = $lot->hold_started_at ?? $lot->created_at;
        if (! $started) {
            return false;
        }

        return Carbon::parse($started)->addDays($holdDays)->lte(now());
    }
}
