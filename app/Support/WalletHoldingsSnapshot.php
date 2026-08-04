<?php

namespace App\Support;

use App\Models\User;
use App\Services\MetalRateService;

/**
 * Canonical gold/silver/SIG wallet snapshot for purchase, SIG activate, rates.push, private WS.
 */
class WalletHoldingsSnapshot
{
    /**
     * @return array<string, mixed>
     */
    public static function make(User $user, ?MetalRateService $rates = null): array
    {
        $assets = AssetsBalancePayload::make($user->fresh(), $rates);
        $withdraw = WithdrawAssetsBroadcastPayload::forUser($user->fresh());

        return [
            'gold_holdings' => (float) data_get($assets, 'gold.grams', 0),
            'silver_holdings' => (float) data_get($assets, 'silver.grams', 0),
            'sig_holdings' => (float) data_get($assets, 'sig.grams', 0),
            'sig_metal_type' => (string) data_get($assets, 'sig.metal_type', 'gold'),
            'sig_invested' => (float) data_get($assets, 'sig.invested', 0),
            'gold_value' => (float) data_get($assets, 'gold.value', 0),
            'silver_value' => (float) data_get($assets, 'silver.value', 0),
            'sig_value' => (float) data_get($assets, 'sig.value', 0),
            'gold_value_display' => (string) data_get($assets, 'gold.value_display', '₹0.00'),
            'silver_value_display' => (string) data_get($assets, 'silver.value_display', '₹0.00'),
            'sig_value_display' => (string) data_get($assets, 'sig.value_display', '₹0.00'),
            'wallet_balance' => (float) data_get($assets, 'wallet_balance', 0),
            'wallet_balance_display' => (string) data_get($assets, 'wallet_balance_display', '₹0.00'),
            'total_assets_balance' => (float) data_get($assets, 'total_assets_balance', 0),
            'total_assets_balance_display' => (string) data_get($assets, 'total_assets_balance_display', '₹0.00'),
            'assets' => $assets,
            'withdraw_assets' => $withdraw,
        ];
    }
}
