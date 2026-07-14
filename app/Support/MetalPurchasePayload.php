<?php

namespace App\Support;

use App\Models\Investment;
use App\Models\User;
use App\Services\AppSettingService;
use App\Services\MetalRateService;

class MetalPurchasePayload
{
    /**
     * @return array<string, mixed>
     */
    public static function config(User $user, MetalRateService $rates, AppSettingService $settings): array
    {
        $metalRates = $rates->getApiRates();

        return [
            'title' => config('buy_metal.title', 'Buy Gold & Silver'),
            'input_modes' => config('buy_metal.input_modes', []),
            'metal_types' => config('buy_metal.metal_types', []),
            'preset_amounts' => config('buy_metal.preset_amounts', []),
            'min_amount' => config('buy_metal.min_amount', 100),
            'min_weight_grams' => config('buy_metal.min_weight_grams', 0.001),
            'max_weight_grams' => config('buy_metal.max_weight_grams', 10000),
            'gst_percent' => $settings->gstRatePercent(),
            'gst_included_for_currency_mode' => (bool) config('buy_metal.gst_included_for_currency_mode', true),
            'payment_methods' => config('buy_metal.payment_methods', []),
            'rates' => $metalRates,
            'wallet_balance' => (float) $user->wallet_balance,
            'wallet_balance_display' => '₹'.number_format((float) $user->wallet_balance, 2),
            'gold_holdings' => (float) $user->gold_holdings,
            'silver_holdings' => (float) $user->silver_holdings,
        ];
    }

    /**
     * @param  array<string, mixed>  $estimate
     * @return array<string, mixed>
     */
    public static function estimate(array $estimate): array
    {
        return [
            'estimate' => $estimate,
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    public static function purchase(array $result): array
    {
        /** @var Investment $investment */
        $investment = $result['investment'];
        $estimate = is_array($result['estimate'] ?? null) ? $result['estimate'] : [];
        $paymentMethod = (string) (
            $result['payment_method']
            ?? (isset($result['payment']) ? $result['payment']->gateway : null)
            ?? ($estimate['payment_method'] ?? 'direct')
        );

        $gstPercent = (float) ($estimate['gst_percent'] ?? 0);
        $amount = (float) ($estimate['amount'] ?? $investment->amount);
        $amountWithGst = (float) ($estimate['total_amount'] ?? $investment->total_amount);
        $weightGrams = (float) ($estimate['weight_grams'] ?? $investment->quantity_grams);

        return [
            // Flat fields matching the mobile purchase request / confirm screen.
            'metal_type' => (string) ($estimate['metal_type'] ?? $investment->metal_type),
            'input_mode' => (string) ($estimate['input_mode'] ?? 'currency'),
            'weight_grams' => $weightGrams,
            'amount' => $amount,
            'gst_percent' => rtrim(rtrim(number_format($gstPercent, 2, '.', ''), '0'), '.').'%',
            'gst_percent_value' => $gstPercent,
            'amount_with_gst' => $amountWithGst,
            'payment_method' => $paymentMethod,
            'transaction_id' => $investment->reference_id,
            'Transaction_id' => $investment->reference_id,

            'purchase' => self::investment($investment),
            'payment' => isset($result['payment']) ? [
                'id' => $result['payment']->id,
                'reference_id' => $result['payment']->reference_id,
                'amount' => (float) $result['payment']->amount,
                'currency' => $result['payment']->currency,
                'status' => $result['payment']->status,
                'gateway' => $result['payment']->gateway,
                'payment_method' => $result['payment']->gateway,
                'paid_at' => optional($result['payment']->paid_at)?->toIso8601String(),
            ] : null,
            'estimate' => $estimate,
            'wallet_balance' => $result['wallet_balance'],
            'wallet_balance_display' => '₹'.number_format((float) $result['wallet_balance'], 2),
            'gold_holdings' => $result['gold_holdings'],
            'silver_holdings' => $result['silver_holdings'],
            'gold_value' => $result['gold_value'] ?? data_get($result, 'assets.gold.value'),
            'silver_value' => $result['silver_value'] ?? data_get($result, 'assets.silver.value'),
            'total_assets_balance' => data_get($result, 'assets.total_assets_balance'),
            'total_assets_balance_display' => data_get($result, 'assets.total_assets_balance_display'),
            'assets' => $result['assets'] ?? null,
            'withdraw_assets' => $result['withdraw_assets'] ?? null,
            'success' => [
                'title' => 'Purchase Successful',
                'message' => 'Your '.ucfirst($investment->metal_type).' purchase has been completed. '
                    .number_format((float) $investment->quantity_grams, 4).'g credited to your '
                    .ucfirst($investment->metal_type).' holdings.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function investment(Investment $investment): array
    {
        return [
            'id' => $investment->id,
            'transaction_id' => $investment->reference_id,
            'Transaction_id' => $investment->reference_id,
            'reference_id' => $investment->reference_id,
            'metal_type' => $investment->metal_type,
            'type' => $investment->type,
            'quantity_grams' => (float) $investment->quantity_grams,
            'rate_per_gram' => (float) $investment->rate_per_gram,
            'amount' => (float) $investment->amount,
            'gst_amount' => (float) $investment->gst_amount,
            'total_amount' => (float) $investment->total_amount,
            'status' => $investment->status,
            'created_at' => $investment->created_at?->toIso8601String(),
        ];
    }
}
