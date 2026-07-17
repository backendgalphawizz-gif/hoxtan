<?php

namespace App\Services;

use App\Models\JewelleryEmiPlan;
use App\Models\JewelleryOrder;
use App\Models\JewelleryOrderEmiInstallment;
use App\Models\User;
use App\Support\DeliveryOtp;
use App\Support\JewelleryEmiPayload;
use App\Support\KycPayload;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JewelleryEmiService
{
    public function __construct(
        protected WalletService $wallet,
        protected ReferralService $referrals,
    ) {}

    /**
     * Preview force-pay of all remaining pending EMIs for an order.
     *
     * @return array<string, mixed>
     */
    public function payAllPreview(JewelleryOrder $order, User $user): array
    {
        $this->assertPayableEmiOrder($order, $user);

        $pending = $order->emiInstallments()
            ->where('status', 'pending')
            ->orderBy('installment_number')
            ->get();

        $amount = round((float) $pending->sum('amount'), 2);
        $walletBalance = round((float) $user->wallet_balance, 2);

        return [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'pending_count' => $pending->count(),
            'amount' => $amount,
            'amount_display' => '₹'.number_format($amount, 2),
            'wallet_balance' => $walletBalance,
            'wallet_balance_display' => '₹'.number_format($walletBalance, 2),
            'can_pay' => $pending->isNotEmpty(),
            'shortfall' => round(max(0, $amount - $walletBalance), 2),
            'default_payment_method' => 'direct',
            'payment_methods' => ['direct', 'wallet'],
            'installments' => $pending->map(fn (JewelleryOrderEmiInstallment $row): array => [
                'id' => $row->id,
                'installment_number' => (int) $row->installment_number,
                'amount' => round((float) $row->amount, 2),
                'due_date' => $row->due_date?->toDateString(),
            ])->values()->all(),
        ];
    }

    /**
     * Pay all remaining EMIs immediately.
     * - direct (default): marks EMIs paid on API hit (no gateway)
     * - wallet: debits wallet then marks EMIs paid
     *
     * @return array<string, mixed>
     */
    public function payAllRemaining(
        JewelleryOrder $order,
        User $user,
        string $paymentMethod = 'direct',
    ): array {
        $this->assertPayableEmiOrder($order, $user);
        KycPayload::assertCanPerformTransactions($user);

        $paymentMethod = strtolower(trim($paymentMethod));

        return match ($paymentMethod) {
            'direct' => $this->settlePayAllDirect($order, $user),
            'wallet' => $this->settlePayAllWithWallet($order, $user),
            default => throw ValidationException::withMessages([
                'payment_method' => ['Supported payment methods: direct, wallet.'],
            ]),
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function settlePayAllDirect(JewelleryOrder $order, User $user): array
    {
        return DB::transaction(function () use ($order, $user): array {
            $pending = $order->emiInstallments()
                ->where('status', 'pending')
                ->orderBy('installment_number')
                ->lockForUpdate()
                ->get();

            if ($pending->isEmpty()) {
                throw ValidationException::withMessages([
                    'emi' => ['All EMIs for this order are already paid.'],
                ]);
            }

            $amount = round((float) $pending->sum('amount'), 2);
            $paidIds = [];

            foreach ($pending as $installment) {
                $this->markInstallmentPaid(
                    $installment,
                    null,
                    'Paid via force pay-all (direct)',
                );
                $paidIds[] = $installment->id;
            }

            $order = $order->fresh([
                'items.product',
                'items.variant',
                'payment',
                'emiInstallments',
                'invoice',
                'driver',
                'user',
            ]);

            return $this->payAllResultPayload(
                amount: $amount,
                installmentsPaid: count($paidIds),
                installmentIds: $paidIds,
                paymentMethod: 'direct',
                order: $order,
                user: $user->fresh(),
            );
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function settlePayAllWithWallet(JewelleryOrder $order, User $user): array
    {
        return DB::transaction(function () use ($order, $user): array {
            $pending = $order->emiInstallments()
                ->where('status', 'pending')
                ->orderBy('installment_number')
                ->lockForUpdate()
                ->get();

            if ($pending->isEmpty()) {
                throw ValidationException::withMessages([
                    'emi' => ['All EMIs for this order are already paid.'],
                ]);
            }

            $amount = round((float) $pending->sum('amount'), 2);
            $user->refresh();
            $walletBalance = round((float) $user->wallet_balance, 2);

            if ($walletBalance < $amount) {
                throw ValidationException::withMessages([
                    'wallet' => [
                        'Insufficient wallet balance. Required ₹'.number_format($amount, 2)
                        .' but available ₹'.number_format($walletBalance, 2).'.',
                    ],
                ]);
            }

            $this->wallet->debit(
                $user,
                $amount,
                'other',
                'Force pay all remaining EMIs for order '.($order->order_number ?: '#'.$order->id),
            );

            $paidIds = [];
            foreach ($pending as $installment) {
                $this->markInstallmentPaid(
                    $installment,
                    null,
                    'Paid via force pay-all (wallet)',
                );
                $paidIds[] = $installment->id;
            }

            $order = $order->fresh([
                'items.product',
                'items.variant',
                'payment',
                'emiInstallments',
                'invoice',
                'driver',
                'user',
            ]);

            return $this->payAllResultPayload(
                amount: $amount,
                installmentsPaid: count($paidIds),
                installmentIds: $paidIds,
                paymentMethod: 'wallet',
                order: $order,
                user: $user->fresh(),
            );
        });
    }

    /**
     * @param  list<int>  $installmentIds
     * @return array<string, mixed>
     */
    protected function payAllResultPayload(
        float $amount,
        int $installmentsPaid,
        array $installmentIds,
        string $paymentMethod,
        JewelleryOrder $order,
        User $user,
    ): array {
        return [
            'amount_paid' => $amount,
            'amount_paid_display' => '₹'.number_format($amount, 2),
            'installments_paid' => $installmentsPaid,
            'installment_ids' => $installmentIds,
            'payment_method' => $paymentMethod,
            'wallet_balance' => round((float) $user->wallet_balance, 2),
            'fully_paid' => $order->emiInstallmentsFullyPaid(),
            'delivery_unlocked' => $order->isDeliveryEligible(),
            'order' => $order,
        ];
    }

    /**
     * Admin: mark all pending EMIs paid without wallet debit.
     *
     * @return array{paid_count: int, order: JewelleryOrder}
     */
    public function markAllPendingPaid(JewelleryOrder $order, ?int $adminId = null, ?string $notes = null): array
    {
        if (! $order->isEmi()) {
            throw ValidationException::withMessages([
                'order' => ['This order is not an EMI order.'],
            ]);
        }

        if (in_array($order->status, ['cancelled', 'failed'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Cannot mark EMI as paid on a cancelled order.'],
            ]);
        }

        return DB::transaction(function () use ($order, $adminId, $notes): array {
            $pending = $order->emiInstallments()
                ->where('status', 'pending')
                ->orderBy('installment_number')
                ->lockForUpdate()
                ->get();

            foreach ($pending as $installment) {
                $this->markInstallmentPaid(
                    $installment,
                    $adminId,
                    $notes ?? 'Marked all remaining EMIs paid by admin',
                );
            }

            return [
                'paid_count' => $pending->count(),
                'order' => $order->fresh(['emiInstallments', 'payment', 'user']),
            ];
        });
    }

    protected function assertPayableEmiOrder(JewelleryOrder $order, User $user): void
    {
        if ($order->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'order' => ['Order not found.'],
            ])->status(404);
        }

        if (! $order->isEmi()) {
            throw ValidationException::withMessages([
                'order' => ['This order is not an EMI order.'],
            ]);
        }

        if (in_array($order->status, ['cancelled', 'failed', 'cart'], true)) {
            throw ValidationException::withMessages([
                'order' => ['EMIs cannot be paid for this order status.'],
            ]);
        }
    }

    /**
     * @return Collection<int, JewelleryEmiPlan>
     */
    public function activePlans(): Collection
    {
        return JewelleryEmiPlan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('tenure_months')
            ->get();
    }

    /**
     * Create monthly EMI rows for an order (all start as pending).
     */
    public function createInstallmentSchedule(JewelleryOrder $order): void
    {
        $tenure = max(1, (int) ($order->emi_tenure ?? 0));
        $total = round((float) ($order->total_emi_cost ?? 0), 2);

        if ($tenure < 1 || $total <= 0) {
            return;
        }

        $monthly = round($total / $tenure, 2);
        $allocated = 0.0;

        for ($number = 1; $number <= $tenure; $number++) {
            $amount = $number === $tenure
                ? round($total - $allocated, 2)
                : $monthly;
            $allocated = round($allocated + $amount, 2);

            JewelleryOrderEmiInstallment::query()->create([
                'jewellery_order_id' => $order->id,
                'installment_number' => $number,
                'amount' => $amount,
                'due_date' => now()->startOfDay()->addMonths($number - 1)->toDateString(),
                'status' => 'pending',
            ]);
        }
    }

    /**
     * Mark one installment as paid. When all are paid, release delivery (OTP + expected date).
     */
    public function markInstallmentPaid(
        JewelleryOrderEmiInstallment $installment,
        ?int $adminId = null,
        ?string $notes = null,
    ): JewelleryOrderEmiInstallment {
        if ($installment->isPaid()) {
            return $installment;
        }

        $order = $installment->order;

        if ($order && in_array($order->status, ['cancelled', 'failed'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Cannot mark EMI as paid on a cancelled order.'],
            ]);
        }

        $installment->update([
            'status' => 'paid',
            'paid_at' => now(),
            'marked_paid_by' => $adminId,
            'notes' => $notes ?? $installment->notes,
        ]);

        $order = $installment->order()->with(['emiInstallments', 'payment', 'items.product', 'user'])->first();

        if ($order && $order->emiInstallmentsFullyPaid()) {
            if ($order->payment && $order->payment->status !== 'completed') {
                $order->payment->update([
                    'status' => 'completed',
                    'amount' => (float) ($order->total_emi_cost ?? $order->total_amount),
                    'paid_at' => now(),
                ]);
            }

            // Do not auto-move to Ready for Delivery — customer must call deliver API.
            app(InvoiceService::class)->generateForJewelleryOrder($order->fresh([
                'items.product',
                'payment',
                'emiInstallments',
                'user',
            ]));
        }

        if ($order?->user) {
            $this->referrals->evaluatePendingBonusAfterCommit($order->user);
        }

        return $installment->fresh();
    }

    /**
     * Mark installment back to pending (admin correction).
     */
    public function markInstallmentPending(JewelleryOrderEmiInstallment $installment): JewelleryOrderEmiInstallment
    {
        $installment->update([
            'status' => 'pending',
            'paid_at' => null,
            'marked_paid_by' => null,
        ]);

        return $installment->fresh();
    }

    /**
     * Customer requests jewellery delivery after all EMIs are paid.
     * Moves tracking to Ready for Delivery (does not auto-run when EMI completes).
     *
     * @return array{order: JewelleryOrder}
     */
    public function requestDelivery(JewelleryOrder $order, User $user): array
    {
        $this->assertPayableEmiOrder($order, $user);

        if (! $order->emiInstallmentsFullyPaid()) {
            throw ValidationException::withMessages([
                'emi' => ['All EMI installments must be paid before requesting delivery.'],
            ]);
        }

        if ($order->hasRequestedDelivery()) {
            throw ValidationException::withMessages([
                'delivery' => ['Delivery has already been requested for this order.'],
            ]);
        }

        if (in_array($order->status, ['cancelled', 'failed', 'completed'], true) || filled($order->delivered_at)) {
            throw ValidationException::withMessages([
                'order' => ['Delivery cannot be requested for this order.'],
            ]);
        }

        return DB::transaction(function () use ($order): array {
            $order->update([
                'delivery_requested_at' => now(),
            ]);

            $this->releaseEmiDelivery($order->fresh());

            return [
                'order' => $order->fresh([
                    'items.product',
                    'items.variant',
                    'payment',
                    'emiInstallments',
                    'invoice',
                    'driver',
                    'user',
                ]),
            ];
        });
    }

    public function releaseEmiDelivery(JewelleryOrder $order): void
    {
        if (! $order->isEmi() || ! $order->emiInstallmentsFullyPaid()) {
            return;
        }

        if (! $order->hasRequestedDelivery()) {
            return;
        }

        $updates = [];

        if (blank($order->delivery_otp)) {
            $updates['delivery_otp'] = DeliveryOtp::generate();
        }

        if ($order->expected_delivery_date === null) {
            $days = max(1, (int) app(AppSettingService::class)->jewelleryDeliveryDays());
            $updates['expected_delivery_date'] = now()->addDays($days)->toDateString();
        }

        if ($updates !== []) {
            $order->update($updates);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPlans(?float $orderAmount = null): array
    {
        $plans = $this->activePlans();

        if ($orderAmount !== null && $orderAmount > 0) {
            return $plans
                ->filter(fn (JewelleryEmiPlan $plan): bool => $this->isEligible($plan, $orderAmount))
                ->map(fn (JewelleryEmiPlan $plan): array => JewelleryEmiPayload::option($plan, $orderAmount))
                ->values()
                ->all();
        }

        return $plans
            ->map(fn (JewelleryEmiPlan $plan): array => JewelleryEmiPayload::plan($plan))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function optionsForAmount(float $orderTotal): array
    {
        return $this->listPlans($orderTotal);
    }

    /**
     * @return array{
     *     plan: JewelleryEmiPlan,
     *     tenure_months: int,
     *     interest_rate_percent: float,
     *     total_emi_cost: float,
     *     monthly_emi_amount: float
     * }
     */
    public function resolveSelection(int $emiPlanId, float $orderTotal): array
    {
        $plan = JewelleryEmiPlan::query()
            ->where('is_active', true)
            ->find($emiPlanId);

        if (! $plan) {
            throw ValidationException::withMessages([
                'emi_plan_id' => ['Selected EMI plan is not available.'],
            ]);
        }

        if (! $this->isEligible($plan, $orderTotal)) {
            throw ValidationException::withMessages([
                'emi_plan_id' => ['Order amount does not meet the minimum for this EMI plan.'],
            ]);
        }

        $calculation = $plan->calculateForAmount($orderTotal);

        return [
            'plan' => $plan,
            ...$calculation,
        ];
    }

    /**
     * @return array{
     *     plan: ?JewelleryEmiPlan,
     *     tenure_months: int,
     *     interest_rate_percent: float,
     *     total_emi_cost: float,
     *     monthly_emi_amount: float
     * }
     */
    public function resolveForCheckout(
        float $orderTotal,
        ?int $emiPlanId = null,
        ?int $tenure = null,
        ?float $totalEmiCost = null,
    ): array {
        if ($emiPlanId !== null) {
            return $this->resolveSelection($emiPlanId, $orderTotal);
        }

        if ($tenure === null || $totalEmiCost === null) {
            throw ValidationException::withMessages([
                'tenure' => ['Tenure is required for EMI checkout.'],
                'total_emi_cost' => ['Total EMI cost is required for EMI checkout.'],
            ]);
        }

        return $this->resolveDirect($tenure, $totalEmiCost);
    }

    /**
     * @return array{
     *     plan: null,
     *     tenure_months: int,
     *     interest_rate_percent: float,
     *     total_emi_cost: float,
     *     monthly_emi_amount: float
     * }
     */
    public function resolveDirect(int $tenure, float $totalEmiCost): array
    {
        $tenureMonths = max(1, $tenure);
        $total = round($totalEmiCost, 2);

        return [
            'plan' => null,
            'tenure_months' => $tenureMonths,
            'interest_rate_percent' => 0,
            'total_emi_cost' => $total,
            'monthly_emi_amount' => round($total / $tenureMonths, 2),
        ];
    }

    public function isEligible(JewelleryEmiPlan $plan, float $orderTotal): bool
    {
        if ($plan->min_order_amount === null) {
            return true;
        }

        return $orderTotal >= (float) $plan->min_order_amount;
    }
}
