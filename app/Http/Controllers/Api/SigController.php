<?php

namespace App\Http\Controllers\Api;

use App\Events\UserAssetsUpdated;
use App\Http\Controllers\Controller;
use App\Models\SigInstallment;
use App\Models\SigPlan;
use App\Services\AppSettingService;
use App\Services\MetalRateService;
use App\Services\SigPlanService;
use App\Support\ApiResponse;
use App\Support\SigPayload;
use App\Support\WalletHoldingsSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SigController extends Controller
{
    public function config(MetalRateService $rates, AppSettingService $settings): JsonResponse
    {
        $metalRates = $rates->getApiRates();

        return ApiResponse::success([
            'frequencies' => config('sig.frequencies', []),
            'metal_types' => config('sig.metal_types', []),
            'preset_amounts' => config('sig.preset_amounts', []),
            'min_amount' => config('sig.min_amount', 100),
            'gst_percent' => $settings->gstRatePercent(),
            'gst_included' => (bool) config('buy_metal.gst_included_for_currency_mode', false),
            'gst_note' => (bool) config('buy_metal.gst_included_for_currency_mode', false)
                ? 'GST included '.$settings->gstRatePercent().'%'
                : 'GST '.$settings->gstRatePercent().'% added on metal value',
            'rates' => $metalRates,
            'gold_rate' => $metalRates['gold'] ?? null,
            'silver_rate' => $metalRates['silver'] ?? null,
            'manage_actions' => config('sig.manage_actions', []),
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        $plan = $this->activePlanQuery($request)->with('installments')->first();

        return ApiResponse::success([
            'sig' => $plan ? SigPayload::plan($plan) : null,
            'has_active_plan' => $plan !== null,
            'can_activate' => $plan === null,
        ]);
    }

    public function estimate(Request $request, SigPlanService $service): JsonResponse
    {
        $data = $request->validate([
            'metal_type' => ['required', Rule::in(['gold', 'silver'])],
            'amount' => ['required', 'numeric', 'min:'.config('sig.min_amount', 100)],
            'frequency' => ['nullable', Rule::in(['daily', 'weekly', 'monthly'])],
        ]);

        $estimate = $service->estimate((float) $data['amount'], $data['metal_type']);

        if (filled($data['frequency'] ?? null)) {
            $estimate['frequency'] = $data['frequency'];
            $estimate['frequency_label'] = ucfirst($data['frequency']);
        }

        return ApiResponse::success([
            'estimate' => $estimate,
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $limit = (int) ($data['limit'] ?? 20);
        $plan = $this->activePlanQuery($request)->first();

        $query = SigInstallment::query()
            ->where('user_id', $request->user()->id)
            ->with('plan')
            ->latest('processed_at')
            ->latest('scheduled_at')
            ->latest('id');

        if ($plan) {
            $query->where('sig_plan_id', $plan->id);
        }

        $transactions = $query->limit($limit)->get();

        return ApiResponse::success([
            'transactions' => SigPayload::installmentCollection($transactions),
        ]);
    }

    public function activate(Request $request, SigPlanService $service): JsonResponse
    {
        $data = $request->validate([
            'metal_type' => ['required', Rule::in(['gold', 'silver'])],
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'amount' => ['required', 'numeric', 'min:'.config('sig.min_amount', 100)],
            'linked_bank_name' => ['nullable', 'string', 'max:100'],
            'linked_bank_last4' => ['nullable', 'string', 'size:4'],
        ]);

        $existing = SigPlan::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', ['active', 'paused'])
            ->exists();

        if ($existing) {
            return ApiResponse::error(
                'You already have an active SIG plan. Manage it from your SIG screen.',
                [],
                422,
            );
        }

        $plan = $service->activate([
            'user_id' => $request->user()->id,
            'metal_type' => $data['metal_type'],
            'frequency' => $data['frequency'],
            'amount' => $data['amount'],
            'linked_bank_name' => $data['linked_bank_name'] ?? null,
            'linked_bank_last4' => $data['linked_bank_last4'] ?? null,
        ]);

        $plan->load('installments');
        $estimate = $service->estimate((float) $data['amount'], $data['metal_type']);

        // Refresh gold/silver/SIG wallet for rates/push + withdraw/assets + private WS.
        $wallet = WalletHoldingsSnapshot::make($request->user()->fresh());
        UserAssetsUpdated::dispatchSafe(
            (int) $request->user()->id,
            array_merge($wallet['assets'], [
                'withdraw_assets' => $wallet['withdraw_assets'],
                'gold_holdings' => $wallet['gold_holdings'],
                'silver_holdings' => $wallet['silver_holdings'],
                'sig_holdings' => $wallet['sig_holdings'],
                'sig_metal_type' => $wallet['sig_metal_type'],
                'sig_value' => $wallet['sig_value'],
            ]),
            'sig_activate',
        );

        return ApiResponse::success([
            'sig' => SigPayload::plan($plan),
            'estimate' => $estimate,
            'activation' => [
                'title' => 'SIG Activated!',
                'amount' => (float) $plan->amount,
                'amount_display' => SigPayload::amountWithFrequency($plan),
                'message' => 'Your '.strtolower($plan->frequency).' SIG plan is now active.',
            ],
            'sig_holdings' => $wallet['sig_holdings'],
            'sig_value' => $wallet['sig_value'],
            'sig_value_display' => $wallet['sig_value_display'],
            'sig_metal_type' => $wallet['sig_metal_type'],
            'gold_holdings' => $wallet['gold_holdings'],
            'silver_holdings' => $wallet['silver_holdings'],
            'total_assets_balance' => $wallet['total_assets_balance'],
            'total_assets_balance_display' => $wallet['total_assets_balance_display'],
            'assets' => $wallet['assets'],
            'withdraw_assets' => $wallet['withdraw_assets'],
            'user_channel' => 'private-user.'.$request->user()->id,
            'user_event' => 'assets.updated',
        ], 'SIG activated successfully.', 201);
    }

    public function pause(Request $request, SigPlanService $service): JsonResponse
    {
        $plan = $this->requireManageablePlan($request);

        $plan = $service->pause($plan);

        return ApiResponse::success([
            'sig' => SigPayload::plan($plan),
            'modal' => collect(config('sig.manage_actions', []))
                ->firstWhere('key', 'pause')['modal'] ?? null,
        ], 'SIG paused.');
    }

    public function resume(Request $request, SigPlanService $service): JsonResponse
    {
        $plan = SigPlan::query()
            ->where('user_id', $request->user()->id)
            ->where('status', 'paused')
            ->latest('id')
            ->firstOrFail();

        $plan = $service->resume($plan);

        return ApiResponse::success([
            'sig' => SigPayload::plan($plan),
            'modal' => collect(config('sig.manage_actions', []))
                ->firstWhere('key', 'resume')['modal'] ?? null,
            'next_auto_debit_display' => $plan->next_debit_at?->format('d F Y'),
        ], 'SIG resumed.');
    }

    public function stop(Request $request, SigPlanService $service): JsonResponse
    {
        $plan = $this->requireManageablePlan($request);

        $plan = $service->stop($plan);

        return ApiResponse::success([
            'sig' => SigPayload::plan($plan, includeManageActions: false),
            'modal' => collect(config('sig.manage_actions', []))
                ->firstWhere('key', 'stop')['modal'] ?? null,
        ], 'SIG stopped.');
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
}
