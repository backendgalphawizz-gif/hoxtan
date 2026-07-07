<?php

namespace App\Support;

use App\Services\MetalRateService;

class JewelleryPricing
{
    /**
     * @return array{
     *     rate_per_gram: ?float,
     *     metal_value: float,
     *     making_charge_percent: float,
     *     making_charge_amount: float,
     *     total: float,
     * }
     */
    public static function calculate(?string $metalType, mixed $weightGrams, mixed $makingChargePercent = null): array
    {
        $weight = max(0, (float) ($weightGrams ?? 0));
        $makingPercent = max(0, (float) ($makingChargePercent ?? 0));

        if (! in_array($metalType, ['gold', 'silver'], true) || $weight <= 0) {
            return [
                'rate_per_gram' => null,
                'metal_value' => 0.0,
                'making_charge_percent' => $makingPercent,
                'making_charge_amount' => 0.0,
                'total' => 0.0,
            ];
        }

        $rate = app(MetalRateService::class)->getCurrentRatePerGram($metalType);
        $metalValue = round($weight * $rate, 2);
        $makingAmount = $makingPercent > 0
            ? round($metalValue * ($makingPercent / 100), 2)
            : 0.0;

        return [
            'rate_per_gram' => $rate,
            'metal_value' => $metalValue,
            'making_charge_percent' => $makingPercent,
            'making_charge_amount' => $makingAmount,
            'total' => round($metalValue + $makingAmount, 2),
        ];
    }
}
