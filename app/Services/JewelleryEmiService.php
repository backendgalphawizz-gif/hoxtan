<?php

namespace App\Services;

use App\Models\JewelleryEmiPlan;
use App\Models\JewelleryOrder;
use App\Models\JewelleryOrderEmiInstallment;
use App\Support\DeliveryOtp;
use App\Support\JewelleryEmiPayload;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class JewelleryEmiService
{
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

        $installment->update([
            'status' => 'paid',
            'paid_at' => now(),
            'marked_paid_by' => $adminId,
            'notes' => $notes ?? $installment->notes,
        ]);

        $order = $installment->order()->with('emiInstallments')->first();

        if ($order && $order->emiInstallmentsFullyPaid()) {
            $this->releaseEmiDelivery($order);
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

    public function releaseEmiDelivery(JewelleryOrder $order): void
    {
        if (! $order->isEmi() || ! $order->emiInstallmentsFullyPaid()) {
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
