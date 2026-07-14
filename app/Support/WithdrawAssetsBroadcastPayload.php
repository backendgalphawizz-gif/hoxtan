<?php

namespace App\Support;

use App\Models\User;
use App\Services\MetalWithdrawalService;

/**
 * Withdraw-assets slice for rates.push / WebSocket.
 * User grams come from authenticated HTTP; public socket is rate-only.
 */
class WithdrawAssetsBroadcastPayload
{
    /**
     * Rate-only shell for public metal-rates channel.
     * Do NOT send available_grams:null — mobile was wiping wallet after purchase.
     *
     * @param  array<string, mixed>  $ratesPayload  from MetalRateService::getApiRates()
     * @return array<string, mixed>
     */
    public static function fromRates(array $ratesPayload): array
    {
        $goldRate = self::cleanMoney(data_get($ratesPayload, 'gold.rate_per_gram', 0));
        $silverRate = self::cleanMoney(data_get($ratesPayload, 'silver.rate_per_gram', 0));

        $assets = [];
        foreach (config('withdraw.assets', []) as $asset) {
            $key = (string) ($asset['value'] ?? '');
            $rate = match ($key) {
                'silver' => $silverRate,
                default => $goldRate,
            };

            $assets[] = [
                'value' => $key,
                'label' => $asset['label'] ?? ucfirst($key),
                'screen_title' => $asset['screen_title'] ?? ('Withdraw '.($asset['label'] ?? ucfirst($key))),
                'rate_per_gram' => $rate,
                'rate_per_gram_display' => '₹'.number_format($rate, 2).' / gm',
            ];
        }

        return [
            'title' => config('withdraw.select_title', 'Select Assets to Withdraw'),
            'min_amount' => (float) config('withdraw.min_amount', 1000),
            'holding_period_hours' => (int) config('withdraw.holding_period_hours', 48),
            'assets' => $assets,
            'rates' => [
                'gold' => $goldRate,
                'silver' => $silverRate,
            ],
            'replace_rates_only' => true,
            'source_api' => '/api/v1/withdraw/assets',
            'instruction' => 'Public socket: update rate_per_gram only. Keep available_grams/total_grams/locked_grams/bank from authenticated POST /api/v1/rates/push or GET /api/v1/withdraw/assets. Then available_value = available_grams × rate_per_gram. Never set grams to null.',
        ];
    }

    /**
     * Full user withdraw wallet for authenticated rates/push + private assets.updated.
     *
     * @return array<string, mixed>
     */
    public static function forUser(User $user, ?MetalWithdrawalService $withdrawals = null): array
    {
        $withdrawals ??= app(MetalWithdrawalService::class);

        try {
            $payload = $withdrawals->assets($user);

            return array_merge($payload, [
                'replace' => true,
                'authenticated' => true,
                'source_api' => '/api/v1/withdraw/assets',
            ]);
        } catch (\Throwable $e) {
            // e.g. withdrawal hold / blocked — still show holdings from balances.
            $balances = \App\Support\AssetsBalancePayload::make($user);
            $goldRate = (float) data_get($balances, 'gold.rate_per_gram', 0);
            $silverRate = (float) data_get($balances, 'silver.rate_per_gram', 0);

            $assets = [];
            foreach (['gold', 'silver'] as $key) {
                $row = $balances[$key] ?? [];
                $grams = (float) ($row['grams'] ?? 0);
                $rate = (float) ($row['rate_per_gram'] ?? 0);
                $value = round($grams * $rate, 2);
                $assets[] = [
                    'value' => $key,
                    'label' => ucfirst($key),
                    'screen_title' => 'Withdraw '.ucfirst($key),
                    'metal_type' => $key,
                    'total_grams' => $grams,
                    'locked_grams' => $grams,
                    'available_grams' => 0.0,
                    'available_value' => 0.0,
                    'available_value_display' => '₹0.00',
                    'wallet_amount' => $value,
                    'wallet_amount_display' => '₹'.number_format($value, 2),
                    'rate_per_gram' => $rate,
                    'rate_per_gram_display' => '₹'.number_format($rate, 2).' / gm',
                    'can_withdraw' => false,
                ];
            }

            return [
                'title' => config('withdraw.select_title', 'Select Assets to Withdraw'),
                'assets' => $assets,
                'balances' => $balances,
                'rates' => ['gold' => $goldRate, 'silver' => $silverRate],
                'replace' => true,
                'authenticated' => true,
                'warning' => $e->getMessage(),
                'source_api' => '/api/v1/withdraw/assets',
            ];
        }
    }

    protected static function cleanMoney(mixed $value): float
    {
        return (float) number_format((float) $value, 2, '.', '');
    }

    /**
     * Recalculate withdraw asset values after a WebSocket rate update.
     *
     * @param  array{gold: float, silver: float}  $rates
     * @param  list<array<string, mixed>>  $cachedAssets  assets[] from GET /withdraw/assets
     * @return list<array<string, mixed>>
     */
    public static function recalculateAssets(array $rates, array $cachedAssets): array
    {
        $goldRate = (float) ($rates['gold'] ?? 0);
        $silverRate = (float) ($rates['silver'] ?? 0);

        return array_map(function (array $asset) use ($goldRate, $silverRate): array {
            $key = (string) ($asset['value'] ?? '');
            $metalType = (string) ($asset['metal_type'] ?? $key);
            $rate = ($key === 'silver' || $metalType === 'silver') ? $silverRate : $goldRate;
            $availableGrams = (float) ($asset['available_grams'] ?? 0);
            $totalGrams = (float) ($asset['total_grams'] ?? $availableGrams);
            $availableValue = round($availableGrams * $rate, 2);
            $walletAmount = round($totalGrams * $rate, 2);

            return array_merge($asset, [
                'rate_per_gram' => round($rate, 2),
                'rate_per_gram_display' => '₹'.number_format($rate, 2).' / gm',
                'available_value' => $availableValue,
                'available_value_display' => '₹'.number_format($availableValue, 2),
                'wallet_amount' => $walletAmount,
                'wallet_amount_display' => '₹'.number_format($walletAmount, 2),
                'can_withdraw' => $availableValue >= (float) config('withdraw.min_amount', 1000),
            ]);
        }, $cachedAssets);
    }
}
