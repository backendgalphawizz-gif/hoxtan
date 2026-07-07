<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SigPlan;
use App\Services\MetalRateService;
use App\Services\SigPlanService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SigController extends Controller
{
    public function config(): JsonResponse
    {
        return ApiResponse::success([
            'frequencies' => [
                ['value' => 'daily', 'label' => 'Daily'],
                ['value' => 'weekly', 'label' => 'Weekly'],
                ['value' => 'monthly', 'label' => 'Monthly'],
            ],
            'metal_types' => [
                ['value' => 'gold', 'label' => 'Gold 24K 99.9% PURE'],
                ['value' => 'silver', 'label' => 'Silver'],
            ],
            'preset_amounts' => [100, 500, 1000, 2000, 5000],
            'min_amount' => 100,
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        $plan = $this->activePlanQuery($request)->first();

        return ApiResponse::success([
            'sig' => $plan ? $this->planPayload($plan) : null,
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $plan = $this->activePlanQuery($request)->first();

        if (! $plan) {
            return ApiResponse::success(['transactions' => []]);
        }

        $transactions = $plan->installments()
            ->latest('scheduled_at')
            ->limit(50)
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'reference_id' => $item->reference_id,
                'amount' => (float) $item->amount,
                'quantity_grams' => $item->quantity_grams !== null ? (float) $item->quantity_grams : null,
                'status' => $item->status,
                'scheduled_at' => $item->scheduled_at?->toIso8601String(),
                'processed_at' => $item->processed_at?->toIso8601String(),
            ]);

        return ApiResponse::success(['transactions' => $transactions]);
    }

    public function activate(Request $request, SigPlanService $service, MetalRateService $rates): JsonResponse
    {
        $data = $request->validate([
            'metal_type' => ['required', Rule::in(['gold', 'silver'])],
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'amount' => ['required', 'numeric', 'min:100'],
        ]);

        $existing = SigPlan::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', ['active', 'paused'])
            ->exists();

        if ($existing) {
            return ApiResponse::error('You already have an active SIG plan. Manage it from your SIG screen.', 422);
        }

        $plan = $service->activate([
            'user_id' => $request->user()->id,
            'metal_type' => $data['metal_type'],
            'frequency' => $data['frequency'],
            'amount' => $data['amount'],
        ]);

        $rate = $rates->getLiveRate($data['metal_type']);

        return ApiResponse::success([
            'message' => 'SIG activated successfully.',
            'sig' => $this->planPayload($plan->fresh(['installments'])),
            'estimate' => $this->estimate($data['amount'], $rate),
            'current_rate_per_gram' => $rate,
        ]);
    }

    public function pause(Request $request, SigPlanService $service): JsonResponse
    {
        $plan = $this->requireManageablePlan($request);

        return ApiResponse::success([
            'message' => 'SIG paused.',
            'sig' => $this->planPayload($service->pause($plan)),
        ]);
    }

    public function resume(Request $request, SigPlanService $service): JsonResponse
    {
        $plan = SigPlan::query()
            ->where('user_id', $request->user()->id)
            ->where('status', 'paused')
            ->latest('id')
            ->firstOrFail();

        return ApiResponse::success([
            'message' => 'SIG resumed.',
            'sig' => $this->planPayload($service->resume($plan)),
        ]);
    }

    public function stop(Request $request, SigPlanService $service): JsonResponse
    {
        $plan = $this->requireManageablePlan($request);

        return ApiResponse::success([
            'message' => 'SIG stopped.',
            'sig' => $this->planPayload($service->stop($plan)),
        ]);
    }

    private function activePlanQuery(Request $request)
    {
        return SigPlan::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', ['active', 'paused'])
            ->latest('id');
    }

    private function requireManageablePlan(Request $request): SigPlan
    {
        return SigPlan::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', ['active', 'paused'])
            ->latest('id')
            ->firstOrFail();
    }

    private function planPayload(SigPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'plan_number' => $plan->plan_number,
            'title' => $plan->title_label,
            'metal_type' => $plan->metal_type,
            'frequency' => $plan->frequency,
            'amount' => (float) $plan->amount,
            'status' => $plan->status,
            'linked_bank' => $plan->linked_bank_label,
            'next_auto_debit_at' => $plan->next_debit_at?->toIso8601String(),
            'total_invested' => (float) $plan->total_invested,
            'metal_accumulated_grams' => (float) $plan->metal_accumulated_grams,
            'completed_installments' => $plan->completed_installments,
            'total_installments' => $plan->total_installments,
            'progress_label' => $plan->progress_label,
            'activated_at' => $plan->activated_at?->toIso8601String(),
        ];
    }

    private function estimate(float $amount, float $ratePerGram): array
    {
        if ($ratePerGram <= 0) {
            return ['grams' => 0];
        }

        return [
            'grams' => round($amount / $ratePerGram, 4),
        ];
    }
}
