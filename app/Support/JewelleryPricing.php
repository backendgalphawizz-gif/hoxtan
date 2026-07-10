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
     *     subtotal_before_discount: float,
     *     discount_type: ?string,
     *     discount_value: float,
     *     discount_amount: float,
     *     total: float,
     * }
     */
    public static function calculate(
        ?string $metalType,
        mixed $weightGrams,
        mixed $makingChargePercent = null,
        ?string $discountType = null,
        mixed $discountValue = null,
    ): array {
        $weight = max(0, (float) ($weightGrams ?? 0));
        $makingPercent = max(0, (float) ($makingChargePercent ?? 0));

        if (! in_array($metalType, ['gold', 'silver'], true) || $weight <= 0) {
            return self::emptyResult($makingPercent, $discountType, $discountValue);
        }

        $rate = app(MetalRateService::class)->getCurrentRatePerGram($metalType);
        $metalValue = round($weight * $rate, 2);
        $makingAmount = $makingPercent > 0
            ? round($metalValue * ($makingPercent / 100), 2)
            : 0.0;
        $subtotalBeforeDiscount = round($metalValue + $makingAmount, 2);
        $discountAmount = self::discountAmount($subtotalBeforeDiscount, $discountType, $discountValue);

        return [
            'rate_per_gram' => $rate,
            'metal_value' => $metalValue,
            'making_charge_percent' => $makingPercent,
            'making_charge_amount' => $makingAmount,
            'subtotal_before_discount' => $subtotalBeforeDiscount,
            'discount_type' => self::normalizeDiscountType($discountType),
            'discount_value' => max(0, (float) ($discountValue ?? 0)),
            'discount_amount' => $discountAmount,
            'total' => max(0, round($subtotalBeforeDiscount - $discountAmount, 2)),
        ];
    }

    public static function discountAmount(float $baseTotal, ?string $discountType, mixed $discountValue): float
    {
        $type = self::normalizeDiscountType($discountType);
        $value = max(0, (float) ($discountValue ?? 0));

        if ($baseTotal <= 0 || $value <= 0 || $type === null) {
            return 0.0;
        }

        if ($type === 'percent') {
            return round($baseTotal * (min(100, $value) / 100), 2);
        }

        return round(min($baseTotal, $value), 2);
    }

    public static function normalizeDiscountType(?string $discountType): ?string
    {
        return in_array($discountType, ['percent', 'flat'], true) ? $discountType : null;
    }

    /**
     * @return array{
     *     rate_per_gram: ?float,
     *     metal_value: float,
     *     making_charge_percent: float,
     *     making_charge_amount: float,
     *     subtotal_before_discount: float,
     *     discount_type: ?string,
     *     discount_value: float,
     *     discount_amount: float,
     *     total: float,
     * }
     */
    protected static function emptyResult(float $makingPercent, ?string $discountType, mixed $discountValue): array
    {
        return [
            'rate_per_gram' => null,
            'metal_value' => 0.0,
            'making_charge_percent' => $makingPercent,
            'making_charge_amount' => 0.0,
            'subtotal_before_discount' => 0.0,
            'discount_type' => self::normalizeDiscountType($discountType),
            'discount_value' => max(0, (float) ($discountValue ?? 0)),
            'discount_amount' => 0.0,
            'total' => 0.0,
        ];
    }
}
