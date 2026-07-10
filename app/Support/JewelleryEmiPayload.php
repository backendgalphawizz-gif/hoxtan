<?php

namespace App\Support;

use App\Models\JewelleryEmiPlan;
use App\Models\JewelleryOrder;

class JewelleryEmiPayload
{
    public static function option(JewelleryEmiPlan $plan, float $orderTotal): array
    {
        $calculation = $plan->calculateForAmount($orderTotal);

        return [
            'id' => $plan->id,
            'tenure_months' => $calculation['tenure_months'],
            'tenure_label' => $plan->displayLabel(),
            'interest_rate_percent' => $calculation['interest_rate_percent'],
            'total_emi_cost' => $calculation['total_emi_cost'],
            'total_emi_cost_display' => self::inr($calculation['total_emi_cost']),
            'monthly_emi_amount' => $calculation['monthly_emi_amount'],
            'monthly_emi_amount_display' => self::inr($calculation['monthly_emi_amount']),
            'min_order_amount' => $plan->min_order_amount !== null
                ? round((float) $plan->min_order_amount, 2)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function forOrder(JewelleryOrder $order): ?array
    {
        if ($order->payment_mode !== 'emi') {
            return null;
        }

        return [
            'plan_id' => $order->jewellery_emi_plan_id,
            'tenure_months' => $order->emi_tenure,
            'tenure_label' => $order->emi_tenure
                ? $order->emi_tenure.' month'.($order->emi_tenure === 1 ? '' : 's')
                : null,
            'total_emi_cost' => $order->total_emi_cost !== null
                ? round((float) $order->total_emi_cost, 2)
                : null,
            'total_emi_cost_display' => $order->total_emi_cost !== null
                ? self::inr((float) $order->total_emi_cost)
                : null,
            'monthly_emi_amount' => $order->monthly_emi_amount !== null
                ? round((float) $order->monthly_emi_amount, 2)
                : null,
            'monthly_emi_amount_display' => $order->monthly_emi_amount !== null
                ? self::inr((float) $order->monthly_emi_amount)
                : null,
        ];
    }

    protected static function inr(float $amount): string
    {
        return '₹'.number_format($amount, 2);
    }
}
