<?php

namespace App\Support;

use App\Models\SigPlan;
use App\Models\User;
use App\Services\MetalRateService;

class AssetsBalancePayload
{
    /**
     * TOTAL ASSETS BALANCE card for mobile home.
     * Grams come from the user; values are grams × current rate.
     * On WebSocket rates.updated, overwrite rates then recalculate values — never append events.
     *
     * @return array<string, mixed>
     */
    public static function make(User $user, ?MetalRateService $rates = null): array
    {
        $rates ??= app(MetalRateService::class);

        $goldRate = (float) $rates->getCurrentRatePerGram('gold');
        $silverRate = (float) $rates->getCurrentRatePerGram('silver');

        $goldGrams = round((float) $user->gold_holdings, 4);
        $silverGrams = round((float) $user->silver_holdings, 4);

        $sigPlan = SigPlan::query()
            ->where('user_id', $user->id)
            ->where(function ($query): void {
                $query->whereIn('status', ['active', 'paused'])
                    ->orWhere(function ($stopped): void {
                        $stopped->where('status', 'stopped')
                            ->where('metal_accumulated_grams', '>', 0);
                    });
            })
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'paused' THEN 1 ELSE 2 END")
            ->latest('id')
            ->first();

        $sigGrams = round((float) ($sigPlan?->metal_accumulated_grams ?? 0), 6);
        $sigMetal = $sigPlan?->metal_type ?? 'gold';
        $sigRate = $sigMetal === 'silver' ? $silverRate : $goldRate;

        $goldValue = round($goldGrams * $goldRate, 2);
        $silverValue = round($silverGrams * $silverRate, 2);
        $sigValue = round($sigGrams * $sigRate, 2);
        $totalAssets = round($goldValue + $silverValue + $sigValue, 2);
        $walletBalance = round((float) $user->wallet_balance, 2);

        return [
            'wallet_balance' => $walletBalance,
            'wallet_balance_display' => '₹'.number_format($walletBalance, 2),
            'total_assets_balance' => $totalAssets,
            'total_assets_balance_display' => '₹'.number_format($totalAssets, 2),
            'gold' => [
                'label' => 'Gold',
                'grams' => $goldGrams,
                'grams_display' => number_format($goldGrams, 2).'g',
                'rate_per_gram' => $goldRate,
                'value' => $goldValue,
                'value_display' => '₹'.number_format($goldValue, 2),
                'wallet_amount' => $goldValue,
                'wallet_amount_display' => '₹'.number_format($goldValue, 2),
            ],
            'silver' => [
                'label' => 'Silver',
                'grams' => $silverGrams,
                'grams_display' => number_format($silverGrams, 2).'g',
                'rate_per_gram' => $silverRate,
                'value' => $silverValue,
                'value_display' => '₹'.number_format($silverValue, 2),
                'wallet_amount' => $silverValue,
                'wallet_amount_display' => '₹'.number_format($silverValue, 2),
            ],
            'sig' => [
                'label' => 'SIG',
                'metal_type' => $sigMetal,
                'grams' => $sigGrams,
                'grams_display' => rtrim(rtrim(number_format($sigGrams, 6, '.', ''), '0'), '.').'g',
                'rate_per_gram' => $sigRate,
                'value' => $sigValue,
                'value_display' => '₹'.number_format($sigValue, 2),
                'wallet_amount' => $sigValue,
                'wallet_amount_display' => '₹'.number_format($sigValue, 2),
                'invested' => $sigPlan ? round((float) $sigPlan->total_invested, 2) : 0.0,
            ],
            'rates' => [
                'gold' => $goldRate,
                'silver' => $silverRate,
            ],
            'note' => 'Your withdrawal amount is transferred instantly to your registered bank account.',
            'websocket' => [
                'replace' => true,
                'instruction' => 'On rates.updated: overwrite gold/silver rate_per_gram, then recalculate gold/silver/sig values and total. Keep grams + wallet_balance from authenticated rates/push or /profile/assets. Do not append events into a list.',
            ],
        ];
    }

    /**
     * Rate-only assets shell for public WebSocket (no user grams/wallet).
     * Mobile keeps grams/wallet from last authenticated push / profile and recalculates values.
     *
     * @param  array<string, mixed>  $ratesPayload
     * @return array<string, mixed>
     */
    public static function broadcastShellFromRates(array $ratesPayload): array
    {
        $goldRate = self::cleanMoney(data_get($ratesPayload, 'gold.rate_per_gram', 0));
        $silverRate = self::cleanMoney(data_get($ratesPayload, 'silver.rate_per_gram', 0));

        // Rate-only — omit grams/value so clients cannot overwrite wallet with nulls.
        return [
            'gold' => [
                'label' => 'Gold',
                'rate_per_gram' => $goldRate,
            ],
            'silver' => [
                'label' => 'Silver',
                'rate_per_gram' => $silverRate,
            ],
            'sig' => [
                'label' => 'SIG',
                'rate_per_gram' => $goldRate,
            ],
            'rates' => [
                'gold' => $goldRate,
                'silver' => $silverRate,
            ],
            'replace_rates_only' => true,
            'source_api' => '/api/v1/profile/assets',
            'instruction' => 'Public rates only. Keep grams/wallet from authenticated POST /api/v1/rates/push or GET /api/v1/withdraw/assets. value = grams × rate_per_gram.',
        ];
    }

    /**
     * Apply new rates onto a previously cached authenticated assets payload.
     *
     * @param  array{gold: float, silver: float}  $rates
     * @param  array<string, mixed>  $cachedAssets
     * @return array<string, mixed>
     */
    public static function applyRatesToAssets(array $rates, array $cachedAssets): array
    {
        $goldRate = (float) ($rates['gold'] ?? data_get($cachedAssets, 'rates.gold', 0));
        $silverRate = (float) ($rates['silver'] ?? data_get($cachedAssets, 'rates.silver', 0));

        $goldGrams = (float) data_get($cachedAssets, 'gold.grams', 0);
        $silverGrams = (float) data_get($cachedAssets, 'silver.grams', 0);
        $sigGrams = (float) data_get($cachedAssets, 'sig.grams', 0);
        $sigMetal = (data_get($cachedAssets, 'sig.metal_type') === 'silver') ? 'silver' : 'gold';
        $sigRate = $sigMetal === 'silver' ? $silverRate : $goldRate;
        $walletBalance = (float) data_get($cachedAssets, 'wallet_balance', 0);

        $goldValue = round($goldGrams * $goldRate, 2);
        $silverValue = round($silverGrams * $silverRate, 2);
        $sigValue = round($sigGrams * $sigRate, 2);
        $total = round($goldValue + $silverValue + $sigValue, 2);

        return array_merge($cachedAssets, [
            'wallet_balance' => $walletBalance,
            'wallet_balance_display' => '₹'.number_format($walletBalance, 2),
            'total_assets_balance' => $total,
            'total_assets_balance_display' => '₹'.number_format($total, 2),
            'gold' => array_merge((array) data_get($cachedAssets, 'gold', []), [
                'grams' => $goldGrams,
                'rate_per_gram' => $goldRate,
                'value' => $goldValue,
                'value_display' => '₹'.number_format($goldValue, 2),
                'wallet_amount' => $goldValue,
                'wallet_amount_display' => '₹'.number_format($goldValue, 2),
            ]),
            'silver' => array_merge((array) data_get($cachedAssets, 'silver', []), [
                'grams' => $silverGrams,
                'rate_per_gram' => $silverRate,
                'value' => $silverValue,
                'value_display' => '₹'.number_format($silverValue, 2),
                'wallet_amount' => $silverValue,
                'wallet_amount_display' => '₹'.number_format($silverValue, 2),
            ]),
            'sig' => array_merge((array) data_get($cachedAssets, 'sig', []), [
                'grams' => $sigGrams,
                'rate_per_gram' => $sigRate,
                'value' => $sigValue,
                'value_display' => '₹'.number_format($sigValue, 2),
                'wallet_amount' => $sigValue,
                'wallet_amount_display' => '₹'.number_format($sigValue, 2),
            ]),
            'rates' => [
                'gold' => $goldRate,
                'silver' => $silverRate,
            ],
            'replace' => true,
        ]);
    }

    protected static function cleanMoney(mixed $value): float
    {
        return (float) number_format((float) $value, 2, '.', '');
    }

    /**
     * Recalculate asset values from fixed grams + new live rates (Flutter helper shape).
     *
     * @param  array{gold: float, silver: float}  $rates
     * @param  array{gold_grams: float, silver_grams: float, sig_grams: float, sig_metal_type?: string, wallet_balance?: float}  $holdings
     * @return array<string, mixed>
     */
    public static function recalculateFromRates(array $rates, array $holdings): array
    {
        $goldRate = (float) ($rates['gold'] ?? 0);
        $silverRate = (float) ($rates['silver'] ?? 0);
        $goldGrams = (float) ($holdings['gold_grams'] ?? 0);
        $silverGrams = (float) ($holdings['silver_grams'] ?? 0);
        $sigGrams = (float) ($holdings['sig_grams'] ?? 0);
        $sigMetal = ($holdings['sig_metal_type'] ?? 'gold') === 'silver' ? 'silver' : 'gold';
        $sigRate = $sigMetal === 'silver' ? $silverRate : $goldRate;

        $goldValue = round($goldGrams * $goldRate, 2);
        $silverValue = round($silverGrams * $silverRate, 2);
        $sigValue = round($sigGrams * $sigRate, 2);
        $walletBalance = round((float) ($holdings['wallet_balance'] ?? 0), 2);

        return [
            'wallet_balance' => $walletBalance,
            'wallet_balance_display' => '₹'.number_format($walletBalance, 2),
            'total_assets_balance' => round($goldValue + $silverValue + $sigValue, 2),
            'gold' => [
                'grams' => $goldGrams,
                'rate_per_gram' => $goldRate,
                'value' => $goldValue,
                'wallet_amount' => $goldValue,
            ],
            'silver' => [
                'grams' => $silverGrams,
                'rate_per_gram' => $silverRate,
                'value' => $silverValue,
                'wallet_amount' => $silverValue,
            ],
            'sig' => [
                'grams' => $sigGrams,
                'rate_per_gram' => $sigRate,
                'value' => $sigValue,
                'wallet_amount' => $sigValue,
            ],
        ];
    }
}
