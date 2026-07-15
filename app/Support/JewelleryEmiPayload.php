<?php

namespace App\Support;

use App\Models\JewelleryEmiPlan;
use App\Models\JewelleryOrder;
use App\Models\JewelleryOrderEmiInstallment;
use App\Services\JewelleryEmiCancellationService;
use Illuminate\Support\Collection;

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
     * Full EMI block for order list / track screen.
     *
     * @return array<string, mixed>|null
     */
    public static function forOrder(JewelleryOrder $order): ?array
    {
        if ($order->payment_mode !== 'emi') {
            return null;
        }

        /** @var Collection<int, JewelleryOrderEmiInstallment> $installments */
        $installments = $order->relationLoaded('emiInstallments')
            ? $order->emiInstallments
            : $order->emiInstallments()->get();

        $paidInstallments = $installments->where('status', 'paid');
        $pendingInstallments = $installments->where('status', 'pending');
        $paidCount = $paidInstallments->count();
        $totalCount = max($installments->count(), (int) ($order->emi_tenure ?? 0));
        $paidAmount = round((float) $paidInstallments->sum('amount'), 2);
        $remainingAmount = round((float) $pendingInstallments->sum('amount'), 2);
        $totalEmiCost = $order->total_emi_cost !== null
            ? round((float) $order->total_emi_cost, 2)
            : round($paidAmount + $remainingAmount, 2);

        if ($remainingAmount <= 0 && $totalEmiCost > $paidAmount) {
            $remainingAmount = round(max(0, $totalEmiCost - $paidAmount), 2);
        }

        $lastPaid = $paidInstallments->sortByDesc(fn (JewelleryOrderEmiInstallment $row) => $row->paid_at?->timestamp ?? 0)->first();
        $nextDue = $pendingInstallments->sortBy(fn (JewelleryOrderEmiInstallment $row) => $row->due_date?->timestamp ?? PHP_INT_MAX)->first();

        $fullyPaid = $order->emiInstallmentsFullyPaid();
        $cancellation = app(JewelleryEmiCancellationService::class);
        $paidForPreview = $paidAmount;
        $preview = [
            ...$cancellation->calculateBreakdown($paidForPreview),
            'can_cancel' => false,
            'reason' => null,
            'bank' => null,
            'auto_approve_hours' => JewelleryEmiCancellationService::AUTO_APPROVE_HOURS,
        ];

        try {
            $preview = $cancellation->preview($order);
        } catch (\Throwable) {
            // Keep calculated breakdown when preview cannot run (e.g. unusual order state).
        }

        $canCancelOrWithdraw = (bool) ($preview['can_cancel'] ?? false);
        $canDeliver = $fullyPaid
            && ! in_array($order->status, ['cancelled', 'failed', 'completed'], true)
            && blank($order->delivered_at);

        $autoDebit = self::autoDebitAccount($order);

        return [
            'plan_id' => $order->jewellery_emi_plan_id,
            'tenure_months' => $order->emi_tenure,
            'tenure_label' => $order->emi_tenure
                ? $order->emi_tenure.' month'.($order->emi_tenure === 1 ? '' : 's')
                : null,
            'total_emi_cost' => $totalEmiCost,
            'total_emi_cost_display' => self::inr($totalEmiCost),
            'monthly_emi_amount' => $order->monthly_emi_amount !== null
                ? round((float) $order->monthly_emi_amount, 2)
                : null,
            'monthly_emi_amount_display' => $order->monthly_emi_amount !== null
                ? self::inr((float) $order->monthly_emi_amount)
                : null,
            'is_completed' => $fullyPaid,
            'paid_count' => $paidCount,
            'total_count' => $totalCount,
            'paid_amount' => $paidAmount,
            'paid_amount_display' => self::inr($paidAmount),
            'remaining_amount' => $remainingAmount,
            'remaining_amount_display' => self::inr($remainingAmount),
            'progress' => [
                'paid_emi' => $paidAmount,
                'paid_emi_display' => self::inr($paidAmount),
                'remaining' => $remainingAmount,
                'remaining_display' => self::inr($remainingAmount),
                'paid_count' => $paidCount,
                'total_count' => $totalCount,
                'progress_label' => $paidCount.'/'.$totalCount.' Installments Paid',
                'progress_percent' => $totalCount > 0
                    ? round(($paidCount / $totalCount) * 100, 2)
                    : 0.0,
                'last_emi_paid_at' => $lastPaid?->paid_at?->toIso8601String(),
                'last_emi_paid_display' => $lastPaid?->paid_at?->format('d F Y'),
                'next_auto_debit_at' => $nextDue?->due_date?->toDateString(),
                'next_auto_debit_display' => $nextDue?->due_date?->format('d F Y'),
            ],
            'auto_debit_account' => $autoDebit,
            'can_cancel' => $canCancelOrWithdraw && ! $fullyPaid,
            'can_withdraw' => $canCancelOrWithdraw,
            'can_deliver' => $canDeliver,
            'delivery_eligible' => $order->isDeliveryEligible(),
            'delivery_hold_message' => $order->isDeliveryEligible()
                ? null
                : 'Jewellery will be delivered after all EMI installments are paid.',
            'actions' => self::actions($canCancelOrWithdraw, $fullyPaid, $canDeliver),
            'cancel_popup' => self::cancelPopup($preview),
            'withdrawal' => self::withdrawalPreview($order, $preview, $autoDebit),
            'installments' => $installments->map(fn (JewelleryOrderEmiInstallment $row) => [
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

    /**
     * @return list<array{key: string, label: string, enabled: bool, endpoint: ?string, method: ?string}>
     */
    protected static function actions(bool $canCancelOrWithdraw, bool $fullyPaid, bool $canDeliver): array
    {
        $actions = [];

        if ($fullyPaid) {
            $actions[] = [
                'key' => 'deliver_jewellery',
                'label' => 'Deliver My Jewellery',
                'enabled' => $canDeliver,
                'endpoint' => null,
                'method' => null,
                'note' => $canDeliver
                    ? 'Request delivery once EMI is fully paid. Our team will assign a delivery slot.'
                    : 'Delivery is not available for this order right now.',
            ];
            $actions[] = [
                'key' => 'withdraw_emi_value',
                'label' => 'Withdraw EMI Value',
                'enabled' => $canCancelOrWithdraw,
                'endpoint' => '/api/v1/orders/{order}/emi-cancel',
                'method' => 'POST',
                'preview_endpoint' => '/api/v1/orders/{order}/emi-cancel-preview',
            ];
        } else {
            $actions[] = [
                'key' => 'cancel_emi_plan',
                'label' => 'Cancel EMI Plan',
                'enabled' => $canCancelOrWithdraw,
                'endpoint' => '/api/v1/orders/{order}/emi-cancel',
                'method' => 'POST',
                'preview_endpoint' => '/api/v1/orders/{order}/emi-cancel-preview',
            ];
            $actions[] = [
                'key' => 'withdraw_emi_value',
                'label' => 'Withdraw EMI Value',
                'enabled' => $canCancelOrWithdraw,
                'endpoint' => '/api/v1/orders/{order}/emi-cancel',
                'method' => 'POST',
                'preview_endpoint' => '/api/v1/orders/{order}/emi-cancel-preview',
            ];
        }

        return $actions;
    }

    /**
     * @param  array<string, mixed>  $preview
     * @return array<string, mixed>
     */
    protected static function cancelPopup(array $preview): array
    {
        $feePercent = (float) ($preview['cancellation_fee_percent'] ?? JewelleryEmiCancellationService::FEE_PERCENT);

        return [
            'can_cancel' => (bool) ($preview['can_cancel'] ?? false),
            'reason' => $preview['reason'] ?? null,
            'title' => 'Cancel EMI Plan?',
            'message' => sprintf(
                'This action cannot be undone. A cancellation deduction of %s%% from paid EMI amount will be applied.',
                rtrim(rtrim(number_format($feePercent, 2, '.', ''), '0'), '.')
            ),
            'confirm_label' => 'Yes, Cancel',
            'dismiss_label' => 'Cancel',
            'fee_percent' => $feePercent,
            'gst_percent' => (float) ($preview['gst_percent'] ?? JewelleryEmiCancellationService::GST_PERCENT),
        ];
    }

    /**
     * @param  array<string, mixed>  $preview
     * @param  array<string, mixed>|null  $autoDebit
     * @return array<string, mixed>
     */
    protected static function withdrawalPreview(JewelleryOrder $order, array $preview, ?array $autoDebit): array
    {
        $orderValue = round((float) ($order->total_emi_cost ?? $order->total_amount), 2);
        $paidAmount = (float) ($preview['paid_amount'] ?? 0);
        $deduction = (float) ($preview['deduction_amount'] ?? 0);
        $refund = (float) ($preview['refund_amount'] ?? 0);
        $feePercent = (float) ($preview['cancellation_fee_percent'] ?? JewelleryEmiCancellationService::FEE_PERCENT);

        return [
            'can_withdraw' => (bool) ($preview['can_cancel'] ?? false),
            'reason' => $preview['reason'] ?? null,
            'order_value' => $orderValue,
            'order_value_display' => self::inr($orderValue),
            'paid_amount' => $paidAmount,
            'paid_amount_display' => self::inr($paidAmount),
            'deduction_percent' => $feePercent,
            'deduction_amount' => $deduction,
            'deduction_amount_display' => self::inr($deduction),
            'deduction_label' => 'Deduction ('.$feePercent.'% + GST)',
            'cancellation_fee_amount' => (float) ($preview['cancellation_fee_amount'] ?? 0),
            'gst_amount' => (float) ($preview['gst_amount'] ?? 0),
            'you_will_receive' => $refund,
            'you_will_receive_display' => self::inr($refund),
            'credit_note' => 'Amount will be credited to your bank account within 3-5 business days.',
            'withdraw_to' => $autoDebit,
            'confirm_label' => 'Confirm Withdrawal',
            'preview_endpoint' => '/api/v1/orders/{order}/emi-cancel-preview',
            'confirm_endpoint' => '/api/v1/orders/{order}/emi-cancel',
            'confirm_method' => 'POST',
        ];
    }

    /**
     * @return array{bank_name: string, account_number_masked: string, display: string}|null
     */
    protected static function autoDebitAccount(JewelleryOrder $order): ?array
    {
        $order->loadMissing('user.kycDetail');
        $kyc = $order->user?->kycDetail;

        if (! $kyc || blank($kyc->account_number)) {
            return null;
        }

        $masked = KycPayload::maskAccount((string) $kyc->account_number);
        $bankName = (string) ($kyc->bank_name ?: 'Bank Account');
        $last4 = substr((string) $kyc->account_number, -4);

        return [
            'bank_name' => $bankName,
            'account_holder_name' => (string) $kyc->account_holder_name,
            'account_number_masked' => $masked,
            'ifsc_code' => (string) $kyc->ifsc_code,
            'display' => trim($bankName.' •••• '.$last4),
        ];
    }

    protected static function inr(float $amount): string
    {
        return '₹'.number_format($amount, 2);
    }
}
