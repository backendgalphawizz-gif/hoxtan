<?php

namespace App\Support;

use App\Models\JewelleryEmiPlan;
use App\Models\JewelleryOrder;

class JewelleryEmiPayload
{
    /**
     * Plan list item (no order amount required).
     *
     * @return array<string, mixed>
     */
    public static function plan(JewelleryEmiPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'tenure_months' => (int) $plan->tenure_months,
            'tenure_label' => $plan->displayLabel(),
            'label' => $plan->label,
            'interest_rate_percent' => round((float) $plan->interest_rate_percent, 2),
            'min_order_amount' => $plan->min_order_amount !== null
                ? round((float) $plan->min_order_amount, 2)
                : null,
            'min_order_label' => $plan->min_order_amount !== null
                ? self::inr((float) $plan->min_order_amount)
                : 'Any',
            'sort_order' => (int) $plan->sort_order,
            'is_active' => (bool) $plan->is_active,
        ];
    }

    public static function option(JewelleryEmiPlan $plan, float $orderTotal): array
    {
        $calculation = $plan->calculateForAmount($orderTotal);

        return array_merge(self::plan($plan), [
            'total_emi_cost' => $calculation['total_emi_cost'],
            'total_emi_cost_display' => self::inr($calculation['total_emi_cost']),
            'monthly_emi_amount' => $calculation['monthly_emi_amount'],
            'monthly_emi_amount_display' => self::inr($calculation['monthly_emi_amount']),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function forOrder(JewelleryOrder $order): ?array
    {
        if ($order->payment_mode !== 'emi') {
            return null;
        }

        $installments = $order->relationLoaded('emiInstallments')
            ? $order->emiInstallments
            : $order->emiInstallments()->get();

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
            'paid_count' => $installments->where('status', 'paid')->count(),
            'total_count' => $installments->count(),
            'delivery_eligible' => $order->isDeliveryEligible(),
            'delivery_hold_message' => $order->isDeliveryEligible()
                ? null
                : 'Jewellery will be delivered after all EMI installments are paid.',
            'installments' => $installments->map(fn ($row) => [
                'id' => $row->id,
                'installment_number' => $row->installment_number,
                'label' => $row->label(),
                'amount' => round((float) $row->amount, 2),
                'amount_display' => self::inr((float) $row->amount),
                'due_date' => $row->due_date?->toDateString(),
                'due_date_display' => $row->due_date?->format('d M Y'),
                'status' => $row->status,
                'paid_at' => $row->paid_at?->toIso8601String(),
                'paid_at_display' => $row->paid_at?->format('d M Y, h:i A'),
            ])->values()->all(),
        ];
    }

    protected static function inr(float $amount): string
    {
        return '₹'.number_format($amount, 2);
    }
}
