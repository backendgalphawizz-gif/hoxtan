<?php

namespace App\Services;

use App\Models\JewelleryEmiRefundRequest;
use App\Models\JewelleryOrder;
use App\Models\User;
use App\Support\NavigationBadgeCounts;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JewelleryEmiCancellationService
{
    public const FEE_PERCENT = 10.0;

    public const GST_PERCENT = 3.0;

    public const AUTO_APPROVE_HOURS = 2;

    /**
     * @return array{
     *     can_cancel: bool,
     *     reason: ?string,
     *     paid_amount: float,
     *     cancellation_fee_percent: float,
     *     cancellation_fee_amount: float,
     *     gst_percent: float,
     *     gst_amount: float,
     *     deduction_amount: float,
     *     refund_amount: float,
     *     bank: ?array,
     *     auto_approve_hours: int
     * }
     */
    public function preview(JewelleryOrder $order): array
    {
        $this->assertOwnedEmiOrder($order);

        $paidAmount = $this->paidEmiAmount($order);
        $breakdown = $this->calculateBreakdown($paidAmount);
        $blockReason = $this->cancellationBlockReason($order, $paidAmount);
        $bank = $this->bankSnapshot($order->user);

        return [
            'can_cancel' => $blockReason === null,
            'reason' => $blockReason,
            ...$breakdown,
            'bank' => $bank,
            'auto_approve_hours' => self::AUTO_APPROVE_HOURS,
            'note' => 'Cancellation fee is 10% of paid EMI + 3% GST on that fee. Remaining amount goes for refund approval.',
        ];
    }

    /**
     * @return array{refund_request: array, order: JewelleryOrder}
     */
    public function cancel(JewelleryOrder $order, User $user, ?string $reason = null): array
    {
        if ($order->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'order' => ['Order not found.'],
            ])->status(404);
        }

        $this->assertOwnedEmiOrder($order);

        $paidAmount = $this->paidEmiAmount($order);
        $blockReason = $this->cancellationBlockReason($order, $paidAmount);

        if ($blockReason !== null) {
            throw ValidationException::withMessages([
                'order' => [$blockReason],
            ]);
        }

        $bank = $this->bankSnapshot($user);

        if ($bank === null) {
            throw ValidationException::withMessages([
                'bank' => ['Please submit your bank details in KYC before cancelling an EMI order.'],
            ]);
        }

        $breakdown = $this->calculateBreakdown($paidAmount);

        $refundRequest = DB::transaction(function () use ($order, $user, $reason, $bank, $breakdown): JewelleryEmiRefundRequest {
            $order->update(['status' => 'cancelled']);

            $request = JewelleryEmiRefundRequest::query()->create([
                'jewellery_order_id' => $order->id,
                'user_id' => $user->id,
                'paid_amount' => $breakdown['paid_amount'],
                'cancellation_fee_percent' => $breakdown['cancellation_fee_percent'],
                'cancellation_fee_amount' => $breakdown['cancellation_fee_amount'],
                'gst_percent' => $breakdown['gst_percent'],
                'gst_amount' => $breakdown['gst_amount'],
                'deduction_amount' => $breakdown['deduction_amount'],
                'refund_amount' => $breakdown['refund_amount'],
                'bank_name' => $bank['bank_name'],
                'account_holder_name' => $bank['account_holder_name'],
                'account_number' => $bank['account_number'],
                'ifsc_code' => $bank['ifsc_code'],
                'status' => 'pending',
                'requested_at' => now(),
                'auto_approve_at' => now()->addHours(self::AUTO_APPROVE_HOURS),
                'cancellation_reason' => $reason,
            ]);

            return $request;
        });

        NavigationBadgeCounts::clearCache();

        return [
            'refund_request' => $this->requestPayload($refundRequest),
            'order' => $order->fresh(['items.product', 'payment', 'emiInstallments', 'emiRefundRequests']),
        ];
    }

    public function approve(JewelleryEmiRefundRequest $request, ?int $adminId = null, ?string $refundReference = null, bool $auto = false): JewelleryEmiRefundRequest
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'request' => ['Only pending refund requests can be approved.'],
            ]);
        }

        $request->update([
            'status' => $auto ? 'auto_approved' : 'refunded',
            'auto_approved' => $auto,
            'reviewed_at' => now(),
            'reviewed_by' => $auto ? null : $adminId,
            'refund_reference' => $refundReference,
            'refunded_at' => now(),
        ]);

        NavigationBadgeCounts::clearCache();

        return $request->fresh(['order', 'user', 'reviewer']);
    }

    public function reject(JewelleryEmiRefundRequest $request, int $adminId, string $reason): JewelleryEmiRefundRequest
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'request' => ['Only pending refund requests can be rejected.'],
            ]);
        }

        $request->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'reviewed_at' => now(),
            'reviewed_by' => $adminId,
        ]);

        NavigationBadgeCounts::clearCache();

        return $request->fresh(['order', 'user', 'reviewer']);
    }

    public function autoApproveExpired(): int
    {
        $count = 0;

        JewelleryEmiRefundRequest::query()
            ->where('status', 'pending')
            ->where('auto_approve_at', '<=', now())
            ->orderBy('id')
            ->each(function (JewelleryEmiRefundRequest $request) use (&$count): void {
                $this->approve($request, auto: true);
                $count++;
            });

        return $count;
    }

    public function paidEmiAmount(JewelleryOrder $order): float
    {
        return round((float) $order->emiInstallments()->where('status', 'paid')->sum('amount'), 2);
    }

    /**
     * @return array{
     *     paid_amount: float,
     *     cancellation_fee_percent: float,
     *     cancellation_fee_amount: float,
     *     gst_percent: float,
     *     gst_amount: float,
     *     deduction_amount: float,
     *     refund_amount: float
     * }
     */
    public function calculateBreakdown(float $paidAmount): array
    {
        $paid = max(0, round($paidAmount, 2));
        $fee = round($paid * (self::FEE_PERCENT / 100), 2);
        $gst = round($fee * (self::GST_PERCENT / 100), 2);
        $deduction = round($fee + $gst, 2);
        $refund = max(0, round($paid - $deduction, 2));

        return [
            'paid_amount' => $paid,
            'cancellation_fee_percent' => self::FEE_PERCENT,
            'cancellation_fee_amount' => $fee,
            'gst_percent' => self::GST_PERCENT,
            'gst_amount' => $gst,
            'deduction_amount' => $deduction,
            'refund_amount' => $refund,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function requestPayload(JewelleryEmiRefundRequest $request): array
    {
        return [
            'id' => $request->id,
            'reference_id' => $request->reference_id,
            'order_id' => $request->jewellery_order_id,
            'status' => $request->status,
            'paid_amount' => (float) $request->paid_amount,
            'cancellation_fee_percent' => (float) $request->cancellation_fee_percent,
            'cancellation_fee_amount' => (float) $request->cancellation_fee_amount,
            'gst_percent' => (float) $request->gst_percent,
            'gst_amount' => (float) $request->gst_amount,
            'deduction_amount' => (float) $request->deduction_amount,
            'refund_amount' => (float) $request->refund_amount,
            'bank' => [
                'bank_name' => $request->bank_name,
                'account_holder_name' => $request->account_holder_name,
                'account_number_masked' => $request->maskedAccountNumber(),
                'ifsc_code' => $request->ifsc_code,
            ],
            'requested_at' => $request->requested_at?->toIso8601String(),
            'auto_approve_at' => $request->auto_approve_at?->toIso8601String(),
            'auto_approved' => (bool) $request->auto_approved,
            'reviewed_at' => $request->reviewed_at?->toIso8601String(),
            'rejection_reason' => $request->rejection_reason,
            'cancellation_reason' => $request->cancellation_reason,
            'refund_reference' => $request->refund_reference,
            'refunded_at' => $request->refunded_at?->toIso8601String(),
        ];
    }

    protected function assertOwnedEmiOrder(JewelleryOrder $order): void
    {
        if ($order->status === 'cart' || $order->payment_mode !== 'emi') {
            throw ValidationException::withMessages([
                'order' => ['Only EMI jewellery orders can use this cancellation.'],
            ]);
        }
    }

    protected function cancellationBlockReason(JewelleryOrder $order, float $paidAmount): ?string
    {
        if (in_array($order->status, ['cancelled', 'failed'], true)) {
            return 'This order is already cancelled.';
        }

        if ($order->status === 'completed' || filled($order->delivered_at)) {
            return 'Delivered jewellery cannot be cancelled.';
        }

        if (filled($order->driver_id) || filled($order->picked_up_at) || filled($order->dispatched_at)) {
            return 'Order is already in delivery and cannot be cancelled.';
        }

        if ($paidAmount <= 0) {
            return 'No EMI amount has been paid yet. Contact support to cancel.';
        }

        $openRequest = JewelleryEmiRefundRequest::query()
            ->where('jewellery_order_id', $order->id)
            ->whereIn('status', ['pending', 'approved', 'auto_approved', 'refunded'])
            ->exists();

        if ($openRequest) {
            return 'A cancellation / refund request already exists for this order.';
        }

        return null;
    }

    /**
     * @return array{bank_name: string, account_holder_name: string, account_number: string, ifsc_code: string}|null
     */
    protected function bankSnapshot(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        $user->loadMissing('kycDetail');
        $kyc = $user->kycDetail;

        if (! $kyc || blank($kyc->account_number) || blank($kyc->ifsc_code)) {
            return null;
        }

        return [
            'bank_name' => (string) $kyc->bank_name,
            'account_holder_name' => (string) $kyc->account_holder_name,
            'account_number' => (string) $kyc->account_number,
            'ifsc_code' => (string) $kyc->ifsc_code,
        ];
    }
}
