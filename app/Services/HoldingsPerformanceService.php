<?php

namespace App\Services;

use App\Models\Investment;
use App\Models\MetalRate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class HoldingsPerformanceService
{
    public function __construct(
        protected MetalRateService $metalRates,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return [
            'title' => config('holdings.title'),
            'subtitle' => config('holdings.subtitle'),
            'metal_types' => config('holdings.metal_types', []),
            'periods' => config('holdings.periods', []),
            'default_metal_type' => config('holdings.default_metal_type', 'gold'),
            'default_months' => (int) config('holdings.default_months', 12),
            'hold_bonus_percent' => (float) config('holdings.hold_bonus_percent', 1),
            'hold_bonus_after_days' => (int) config('holdings.hold_bonus_after_days', 365),
            'hold_bonus_message' => config('holdings.hold_bonus_message'),
            'series' => [
                ['key' => 'purchase_amount', 'label' => 'Purchase Amount', 'style' => 'dashed'],
                ['key' => 'current_rate_amount', 'label' => 'Current Rate Amount', 'style' => 'solid'],
            ],
            'my_purchases' => config('holdings.my_purchases', []),
            'can_withdraw' => true,
            'withdraw_note' => config('withdraw.holding_period_message'),
            'purchase_endpoint' => '/api/v1/holdings/purchase',
            'purchase_payload_examples' => [
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
            'sell_endpoint' => '/api/v1/holdings/sell',
            'sell_payload_example' => [
                'weight_grams' => 50,
                'payment_method' => 'upi',
                'transaction_id' => 'TXN123',
            ],
            'sell_after_hours' => (int) config('holdings.sell_after_hours', 48),
            'sell_auto_approve_hours' => (int) config('holdings.sell_auto_approve_hours', 2),
            'sell_after_message' => config('holdings.sell_after_message'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function performance(User $user, string $metalType, int $months): array
    {
        $metalType = $this->normalizeMetal($metalType);
        $months = $this->normalizeMonths($months);

        $end = now()->endOfMonth();
        $start = now()->subMonthsNoOverflow($months - 1)->startOfMonth();

        $investments = Investment::query()
            ->where('user_id', $user->id)
            ->where('metal_type', $metalType)
            ->where('status', 'completed')
            ->where('created_at', '<=', $end)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get([
                'id',
                'type',
                'quantity_grams',
                'remaining_grams',
                'amount',
                'total_amount',
                'rate_per_gram',
                'purpose',
                'created_at',
            ]);

        $rateHistory = MetalRate::query()
            ->where('metal_type', $metalType)
            ->where('created_at', '<=', $end)
            ->orderBy('created_at')
            ->get(['rate_per_gram', 'created_at']);

        $currentRate = $this->money((float) $this->metalRates->getCurrentRatePerGram($metalType));
        $points = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $monthEnd = $cursor->copy()->endOfMonth();
            if ($monthEnd->gt(now())) {
                $monthEnd = now();
            }

            $isCurrentMonth = $monthEnd->isSameMonth(now()) && $monthEnd->isSameYear(now());
            $snapshot = $isCurrentMonth
                ? $this->currentHoldingsSnapshot($investments)
                : $this->snapshotAt($investments, $monthEnd);

            $rate = $isCurrentMonth
                ? $currentRate
                : $this->money($this->rateAt($rateHistory, $monthEnd, $currentRate));

            $grams = $this->grams($snapshot['grams']);
            // Purchase amount stays as stored in DB (no conversion).
            $purchaseAmount = $this->money($snapshot['invested']);
            // Only this field is converted: grams × live/current month rate.
            $currentRateAmount = $this->money($grams * $rate);

            $points[] = [
                'date' => $monthEnd->toDateString(),
                'label' => strtoupper($monthEnd->format('M')),
                'month' => (int) $monthEnd->format('n'),
                'year' => (int) $monthEnd->format('Y'),
                'grams' => $grams,
                // Aliases kept for older app builds.
                'invested_value' => $purchaseAmount,
                'invested_value_display' => $this->inr($purchaseAmount),
                'market_value' => $currentRateAmount,
                'market_value_display' => $this->inr($currentRateAmount),
                // Explicit fields requested by product.
                'purchase_amount' => $purchaseAmount,
                'purchase_amount_display' => $this->inr($purchaseAmount),
                'current_rate' => $rate,
                'current_rate_display' => $this->inr($rate).' / gm',
                'current_rate_amount' => $currentRateAmount,
                'current_rate_amount_display' => $this->inr($currentRateAmount),
                'rate_per_gram' => $rate,
            ];

            $cursor->addMonthNoOverflow()->startOfMonth();
        }

        $latest = $points[array_key_last($points)] ?? null;
        $firstWithValue = collect($points)->first(fn (array $p): bool => (float) $p['purchase_amount'] > 0);
        $growth = 0.0;
        if ($latest && $firstWithValue && (float) $firstWithValue['current_rate_amount'] > 0) {
            $growth = $this->money(
                (((float) $latest['current_rate_amount'] - (float) $firstWithValue['current_rate_amount'])
                    / (float) $firstWithValue['current_rate_amount']) * 100
            );
        }

        return [
            'title' => config('holdings.title'),
            'subtitle' => config('holdings.subtitle'),
            'metal_type' => $metalType,
            'months' => $months,
            'hold_bonus_percent' => (float) config('holdings.hold_bonus_percent', 1),
            'hold_bonus_after_days' => (int) config('holdings.hold_bonus_after_days', 365),
            'hold_bonus_message' => config('holdings.hold_bonus_message'),
            'withdraw_note' => config('withdraw.holding_period_message'),
            'series' => [
                ['key' => 'purchase_amount', 'label' => 'Purchase Amount', 'style' => 'dashed'],
                ['key' => 'current_rate_amount', 'label' => 'Current Rate Amount', 'style' => 'solid'],
            ],
            'summary' => [
                'current_grams' => (float) ($latest['grams'] ?? 0),
                'purchase_amount' => (float) ($latest['purchase_amount'] ?? 0),
                'purchase_amount_display' => $latest['purchase_amount_display'] ?? '₹0.00',
                'current_rate' => (float) ($latest['current_rate'] ?? $currentRate),
                'current_rate_display' => $latest['current_rate_display'] ?? ($this->inr($currentRate).' / gm'),
                'current_rate_amount' => (float) ($latest['current_rate_amount'] ?? 0),
                'current_rate_amount_display' => $latest['current_rate_amount_display'] ?? '₹0.00',
                // Back-compat aliases.
                'current_market_value' => (float) ($latest['current_rate_amount'] ?? 0),
                'current_market_value_display' => $latest['current_rate_amount_display'] ?? '₹0.00',
                'current_invested_value' => (float) ($latest['purchase_amount'] ?? 0),
                'current_invested_value_display' => $latest['purchase_amount_display'] ?? '₹0.00',
                'growth_percent' => $growth,
                'rate_per_gram' => (float) ($latest['current_rate'] ?? $currentRate),
            ],
            'chart' => [
                'labels' => array_column($points, 'label'),
                'points' => $points,
                'purchase_amounts' => array_map(fn ($p) => $p['purchase_amount'], $points),
                'current_rate_amounts' => array_map(fn ($p) => $p['current_rate_amount'], $points),
                // Back-compat.
                'market_values' => array_map(fn ($p) => $p['current_rate_amount'], $points),
                'invested_values' => array_map(fn ($p) => $p['purchase_amount'], $points),
            ],
            'my_purchases' => config('holdings.my_purchases', []),
        ];
    }

    /**
     * Live holdings: keep DB grams + purchase amount as-is.
     * Only current_rate_amount is computed later (grams × live rate).
     *
     * @param  Collection<int, Investment>  $investments
     * @return array{grams: float, invested: float}
     */
    protected function currentHoldingsSnapshot(Collection $investments): array
    {
        $grams = 0.0;
        $invested = 0.0;

        foreach ($investments as $row) {
            if ($row->type !== 'buy') {
                continue;
            }

            // Use stored remaining/quantity exactly — no rate conversion.
            $remaining = $row->remaining_grams !== null
                ? (float) (string) $row->remaining_grams
                : (float) (string) $row->quantity_grams;

            if ($remaining <= 0) {
                continue;
            }

            $grams += $remaining;

            // Stored purchase amount as-is (skip free bonus metal).
            if (($row->purpose ?? '') === 'hold_bonus') {
                continue;
            }

            $paid = (float) (string) ($row->total_amount > 0 ? $row->total_amount : $row->amount);

            // If partially sold, keep proportional paid amount from the same stored total.
            $purchased = (float) (string) $row->quantity_grams;
            if ($purchased > 0 && $remaining + 0.00005 < $purchased) {
                $invested += round(($remaining / $purchased) * $paid, 2);
            } else {
                $invested += $paid;
            }
        }

        return [
            'grams' => max(0, $grams),
            'invested' => max(0, $invested),
        ];
    }

    /**
     * Historical month-end: stored buy amounts/grams as-is; sells only reduce remaining.
     *
     * @param  Collection<int, Investment>  $investments
     * @return array{grams: float, invested: float}
     */
    protected function snapshotAt(Collection $investments, Carbon $at): array
    {
        $lots = []; // id => [grams, invested]

        foreach ($investments as $row) {
            if ($row->created_at && $row->created_at->greaterThan($at)) {
                continue;
            }

            if ($row->type === 'buy') {
                $qty = (float) (string) $row->quantity_grams;
                $paid = (float) (string) ($row->total_amount > 0 ? $row->total_amount : $row->amount);
                if (($row->purpose ?? '') === 'hold_bonus') {
                    $paid = 0.0;
                }

                $lots[$row->id] = [
                    'grams' => $qty,
                    'invested' => $paid,
                ];

                continue;
            }

            // Sell reduces grams/purchase amount of open lots (no rate conversion).
            $sellLeft = (float) (string) $row->quantity_grams;
            foreach ($lots as $id => $lot) {
                if ($sellLeft <= 0) {
                    break;
                }
                if ($lot['grams'] <= 0) {
                    continue;
                }

                $take = min($lot['grams'], $sellLeft);
                $ratio = $lot['grams'] > 0 ? ($take / $lot['grams']) : 0;
                $lots[$id]['grams'] = max(0, round($lot['grams'] - $take, 4));
                $lots[$id]['invested'] = max(0, round($lot['invested'] * (1 - $ratio), 2));
                $sellLeft = round($sellLeft - $take, 4);
            }
        }

        $grams = 0.0;
        $invested = 0.0;
        foreach ($lots as $lot) {
            $grams += $lot['grams'];
            $invested += $lot['invested'];
        }

        return [
            'grams' => max(0, $grams),
            'invested' => max(0, $invested),
        ];
    }

    /**
     * @param  Collection<int, MetalRate>  $rates
     */
    protected function rateAt(Collection $rates, Carbon $at, float $fallback): float
    {
        $matched = null;

        foreach ($rates as $rate) {
            if ($rate->created_at && $rate->created_at->greaterThan($at)) {
                break;
            }
            $matched = $rate;
        }

        return $matched ? (float) $matched->rate_per_gram : $fallback;
    }

    protected function grams(float $value): float
    {
        return (float) number_format(round($value, 4), 4, '.', '');
    }

    protected function money(float $value): float
    {
        return (float) number_format(round($value, 2), 2, '.', '');
    }

    protected function inr(float $amount): string
    {
        return '₹'.number_format($amount, 2);
    }

    protected function normalizeMetal(string $metalType): string
    {
        $metalType = strtolower($metalType);
        if (! in_array($metalType, ['gold', 'silver'], true)) {
            throw ValidationException::withMessages([
                'metal_type' => ['Metal type must be gold or silver.'],
            ]);
        }

        return $metalType;
    }

    protected function normalizeMonths(int $months): int
    {
        $allowed = collect(config('holdings.periods', []))
            ->pluck('value')
            ->map(fn ($v) => (int) $v)
            ->all();

        if ($allowed === []) {
            $allowed = [12, 24, 36];
        }

        if (! in_array($months, $allowed, true)) {
            throw ValidationException::withMessages([
                'months' => ['Months must be one of: '.implode(', ', $allowed).'.'],
            ]);
        }

        return $months;
    }
}
