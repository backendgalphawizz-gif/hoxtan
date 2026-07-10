<?php

namespace App\Services;

use App\Models\JewelleryEmiPlan;
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
     * @return list<array<string, mixed>>
     */
    public function optionsForAmount(float $orderTotal): array
    {
        return $this->activePlans()
            ->filter(fn (JewelleryEmiPlan $plan): bool => $this->isEligible($plan, $orderTotal))
            ->map(fn (JewelleryEmiPlan $plan): array => JewelleryEmiPayload::option($plan, $orderTotal))
            ->values()
            ->all();
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

    public function isEligible(JewelleryEmiPlan $plan, float $orderTotal): bool
    {
        if ($plan->min_order_amount === null) {
            return true;
        }

        return $orderTotal >= (float) $plan->min_order_amount;
    }
}
