<?php

namespace App\Support;

/**
 * Public WebSocket-friendly slice of GET /api/v1/withdraw/assets.
 * Grams / bank are user-specific — load once from the HTTP API, then overwrite
 * rate_per_gram + available_value on each rates.updated (replace: true).
 */
class WithdrawAssetsBroadcastPayload
{
    /**
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
                // SIG uses gold by default on the public channel; mobile overwrites with user's sig metal_type after first HTTP load.
                default => $goldRate,
            };

            $assets[] = [
                'value' => $key,
                'label' => $asset['label'] ?? ucfirst($key),
                'screen_title' => $asset['screen_title'] ?? ('Withdraw '.($asset['label'] ?? ucfirst($key))),
                // User-specific — keep from GET /withdraw/assets; only rates/values update on socket.
                'available_grams' => null,
                'available_value' => null,
                'rate_per_gram' => $rate,
                'rate_per_gram_display' => '₹'.number_format($rate, 2).' / gm',
            ];
        }

        return [
            'title' => config('withdraw.select_title', 'Select Assets to Withdraw'),
            'min_amount' => (float) config('withdraw.min_amount', 1000),
            'min_amount_note' => config('withdraw.min_amount_note'),
            'note' => config('withdraw.note'),
            'holding_period_hours' => (int) config('withdraw.holding_period_hours', 48),
            'holding_period_message' => config('withdraw.holding_period_message'),
            'hold_bonus_percent' => (float) config('withdraw.hold_bonus_percent', 1),
            'hold_bonus_message' => config('withdraw.hold_bonus_message'),
            'input_modes' => config('withdraw.input_modes', []),
            'preset_amounts' => config('withdraw.preset_amounts', []),
            'auto_approve_hours' => (int) config('withdraw.auto_approve_hours', 2),
            'assets' => $assets,
            'rates' => [
                'gold' => $goldRate,
                'silver' => $silverRate,
            ],
            'replace' => true,
            'source_api' => '/api/v1/withdraw/assets',
            'instruction' => 'Load once from GET /api/v1/withdraw/assets (grams, bank, locked). On rates.updated: overwrite this withdraw_assets object (replace:true), keep available_grams/locked_grams/bank from cache, set rate_per_gram from rates, available_value = available_grams × rate_per_gram. Do not append events.',
        ];
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
            $grams = (float) ($asset['available_grams'] ?? 0);
            $value = round($grams * $rate, 2);

            return array_merge($asset, [
                'rate_per_gram' => round($rate, 2),
                'rate_per_gram_display' => '₹'.number_format($rate, 2).' / gm',
                'available_value' => $value,
                'available_value_display' => '₹'.number_format($value, 2),
                'can_withdraw' => $value >= (float) config('withdraw.min_amount', 1000),
            ]);
        }, $cachedAssets);
    }
}
