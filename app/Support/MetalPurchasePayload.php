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

        return [
            'purchase' => self::investment($investment),
            'estimate' => $result['estimate'],
            'wallet_balance' => $result['wallet_balance'],
            'wallet_balance_display' => '₹'.number_format((float) $result['wallet_balance'], 2),
            'gold_holdings' => $result['gold_holdings'],
            'silver_holdings' => $result['silver_holdings'],
            'success' => [
                'title' => 'Purchase Successful',
                'message' => 'Your '.ucfirst($investment->metal_type).' purchase has been completed.',
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
