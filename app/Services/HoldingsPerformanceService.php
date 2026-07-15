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
            'series' => config('holdings.series', []),
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
                'lot_id' => 12,
                'metal_type' => 'gold',
                'input_mode' => 'weight',
                'weight_grams' => 2,
            ],
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
            ->get(['type', 'quantity_grams', 'amount', 'total_amount', 'created_at']);

        $rateHistory = MetalRate::query()
            ->where('metal_type', $metalType)
            ->where('created_at', '<=', $end)
            ->orderBy('created_at')
            ->get(['rate_per_gram', 'created_at']);

        $currentRate = (float) $this->metalRates->getCurrentRatePerGram($metalType);
        $points = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $monthEnd = $cursor->copy()->endOfMonth();
            if ($monthEnd->gt(now())) {
                $monthEnd = now();
            }

            $snapshot = $this->snapshotAt($investments, $monthEnd);
            $rate = $this->rateAt($rateHistory, $monthEnd, $currentRate);
            $marketValue = round($snapshot['grams'] * $rate, 2);

            $points[] = [
                'date' => $monthEnd->toDateString(),
                'label' => strtoupper($monthEnd->format('M')),
                'month' => (int) $monthEnd->format('n'),
                'year' => (int) $monthEnd->format('Y'),
                'grams' => $snapshot['grams'],
                'invested_value' => $snapshot['invested'],
                'invested_value_display' => '₹'.number_format($snapshot['invested'], 2),
                'market_value' => $marketValue,
                'market_value_display' => '₹'.number_format($marketValue, 2),
                'rate_per_gram' => $rate,
            ];

            $cursor->addMonthNoOverflow()->startOfMonth();
        }

        $latest = $points[array_key_last($points)] ?? null;
        $first = $points[0] ?? null;
        $growth = 0.0;
        if ($latest && $first && (float) $first['market_value'] > 0) {
            $growth = round((((float) $latest['market_value'] - (float) $first['market_value']) / (float) $first['market_value']) * 100, 2);
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
            'series' => config('holdings.series', []),
            'summary' => [
                'current_grams' => (float) ($latest['grams'] ?? 0),
                'current_market_value' => (float) ($latest['market_value'] ?? 0),
                'current_market_value_display' => $latest['market_value_display'] ?? '₹0.00',
                'current_invested_value' => (float) ($latest['invested_value'] ?? 0),
                'current_invested_value_display' => $latest['invested_value_display'] ?? '₹0.00',
                'growth_percent' => $growth,
                'rate_per_gram' => (float) ($latest['rate_per_gram'] ?? $currentRate),
            ],
            'chart' => [
                'labels' => array_column($points, 'label'),
                'points' => $points,
                'market_values' => array_map(fn ($p) => $p['market_value'], $points),
                'invested_values' => array_map(fn ($p) => $p['invested_value'], $points),
            ],
            'my_purchases' => config('holdings.my_purchases', []),
        ];
    }

    /**
     * @param  Collection<int, Investment>  $investments
     * @return array{grams: float, invested: float}
     */
    protected function snapshotAt(Collection $investments, Carbon $at): array
    {
        $grams = 0.0;
        $invested = 0.0;

        foreach ($investments as $row) {
            if ($row->created_at && $row->created_at->greaterThan($at)) {
                continue;
            }

            $qty = (float) $row->quantity_grams;
            if ($row->type === 'buy') {
                $grams += $qty;
                $invested += (float) $row->total_amount;
            } else {
                $grams -= $qty;
                $invested -= (float) $row->amount;
            }
        }

        return [
            'grams' => max(0, round($grams, 4)),
            'invested' => max(0, round($invested, 2)),
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
