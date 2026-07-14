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
            ->whereIn('status', ['active', 'paused'])
            ->latest('id')
            ->first();

        $sigGrams = round((float) ($sigPlan?->metal_accumulated_grams ?? 0), 4);
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
            ],
            'silver' => [
                'label' => 'Silver',
                'grams' => $silverGrams,
                'grams_display' => number_format($silverGrams, 2).'g',
                'rate_per_gram' => $silverRate,
                'value' => $silverValue,
                'value_display' => '₹'.number_format($silverValue, 2),
            ],
            'sig' => [
                'label' => 'SIG',
                'metal_type' => $sigMetal,
                'grams' => $sigGrams,
                'grams_display' => number_format($sigGrams, 2).'g',
                'rate_per_gram' => $sigRate,
                'value' => $sigValue,
                'value_display' => '₹'.number_format($sigValue, 2),
                'invested' => $sigPlan ? round((float) $sigPlan->total_invested, 2) : 0.0,
            ],
            'rates' => [
                'gold' => $goldRate,
                'silver' => $silverRate,
            ],
            'note' => 'Your withdrawal amount is transferred instantly to your registered bank account.',
            'websocket' => [
                'replace' => true,
                'instruction' => 'On rates.updated: overwrite gold/silver rate_per_gram, then recalculate gold/silver/sig values and total. Do not append events into a list.',
            ],
        ];
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

        return [
            'wallet_balance' => round((float) ($holdings['wallet_balance'] ?? 0), 2),
            'total_assets_balance' => round($goldValue + $silverValue + $sigValue, 2),
            'gold' => ['grams' => $goldGrams, 'rate_per_gram' => $goldRate, 'value' => $goldValue],
            'silver' => ['grams' => $silverGrams, 'rate_per_gram' => $silverRate, 'value' => $silverValue],
            'sig' => ['grams' => $sigGrams, 'rate_per_gram' => $sigRate, 'value' => $sigValue],
        ];
    }
}
