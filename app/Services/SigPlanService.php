<?php

namespace App\Services;

use App\Models\MetalWithdrawal;
use App\Models\SigInstallment;
use App\Models\SigPlan;
use App\Models\User;
use App\Services\GstService;
use App\Services\MetalRateService;
use App\Support\KycPayload;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SigPlanService
{
    public function __construct(
        protected MetalRateService $metalRates,
        protected GstService $gst,
        protected ReferralService $referrals,
    ) {}

    /**
     * @return array{
     *     metal_type: string,
     *     amount: float,
     *     rate_per_gram: float,
     *     rate_per_gram_display: string,
     *     gst_percent: float,
     *     gst_included: bool,
     *     gst_note: string,
     *     taxable_amount: float,
     *     gst_amount: float,
     *     gold_grams: float,
     *     gold_grams_display: string
     * }
     */
    public function estimate(float $amount, string $metalType, ?float $weightGrams = null): array
    {
        $rate = $this->metalRates->getCurrentRatePerGram($metalType);
        $gstPercent = $this->gst->ratePercent();

        // SIG wallet credits full entered amount as metal value (GST is on payment only).
        $taxableAmount = round($amount, 2);
        $gstBreakup = $this->gst->calculateGstAmount($taxableAmount);
        $gstAmount = $gstBreakup['gst_amount'];
        $totalAmount = $gstBreakup['total'];

        if ($weightGrams !== null && $weightGrams > 0) {
            $grams = round($weightGrams, 6);
        } else {
            // 6dp so ₹100 ≈ grams×rate (4dp caused ~₹96 after GST bugs / rounding).
            $grams = $rate > 0 ? round($taxableAmount / $rate, 6) : 0.0;
        }

        return [
            'metal_type' => $metalType,
            'amount' => $taxableAmount,
            'amount_display' => '₹'.number_format($taxableAmount, 0),
            'rate_per_gram' => round($rate, 2),
            'rate_per_gram_display' => '₹'.number_format($rate, 0).' / gm',
            'gst_percent' => $gstPercent,
            'gst_included' => false,
            'gst_note' => 'GST '.$gstPercent.'% added on metal value',
            'taxable_amount' => $taxableAmount,
            'gst_amount' => $gstAmount,
            'amount_with_gst' => $totalAmount,
            'amount_with_gst_display' => '₹'.number_format($totalAmount, 2),
            'gold_grams' => $grams,
            'gold_grams_display' => rtrim(rtrim(number_format($grams, 6, '.', ''), '0'), '.').' g Gold',
            'metal_grams' => $grams,
            'metal_grams_display' => rtrim(rtrim(number_format($grams, 6, '.', ''), '0'), '.').' g '.ucfirst($metalType),
            'wallet_amount' => $taxableAmount,
            'wallet_amount_display' => '₹'.number_format($taxableAmount, 2),
        ];
    }

    public function activate(array $data, ?int $adminId = null): SigPlan
    {
        return DB::transaction(function () use ($data, $adminId): SigPlan {
            /** @var User $user */
            $user = User::query()->with('kycDetail')->findOrFail($data['user_id']);
            KycPayload::assertCanPerformTransactions($user);

            $plan = SigPlan::query()->create([
                'user_id' => $user->id,
                'metal_type' => $data['metal_type'] ?? 'gold',
                'frequency' => $data['frequency'],
                'amount' => $data['amount'],
                'status' => 'active',
                'linked_bank_name' => $data['linked_bank_name'] ?? $user->kycDetail?->bank_name,
                'linked_bank_last4' => $data['linked_bank_last4'] ?? $this->bankLast4($user->kycDetail?->account_number),
                'total_installments' => $data['total_installments'] ?? $this->defaultInstallmentCount($data['frequency']),
                'activated_at' => now(),
                'next_debit_at' => $this->nextDebitAt($data['frequency']),
                'admin_notes' => $data['admin_notes'] ?? null,
                'created_by' => $adminId,
            ]);

            if ($data['record_initial_installment'] ?? true) {
                $estimate = $this->estimate(
                    (float) $data['amount'],
                    $plan->metal_type,
                    isset($data['weight_grams']) ? (float) $data['weight_grams'] : null,
                );

                $this->recordInstallment($plan, [
                    'amount' => $data['amount'],
                    'quantity_grams' => $estimate['metal_grams'],
                    'rate_per_gram' => $estimate['rate_per_gram'],
                    'status' => 'success',
                    'scheduled_at' => now(),
                ]);
            }

            return $plan->fresh(['user', 'installments']);
        });
    }

    public function pause(SigPlan $plan): SigPlan
    {
        if ($plan->status !== 'active') {
            return $plan;
        }

        $plan->update([
            'status' => 'paused',
            'paused_at' => now(),
        ]);

        return $plan->fresh();
    }

    public function resume(SigPlan $plan): SigPlan
    {
        if ($plan->status !== 'paused') {
            return $plan;
        }

        $plan->update([
            'status' => 'active',
            'paused_at' => null,
            'next_debit_at' => $this->nextDebitAt($plan->frequency),
        ]);

        return $plan->fresh();
    }

    public function stop(SigPlan $plan): SigPlan
    {
        if ($plan->status === 'stopped') {
            return $plan;
        }

        $plan->update([
            'status' => 'stopped',
            'stopped_at' => now(),
            'next_debit_at' => null,
        ]);

        return $plan->fresh();
    }

    public function syncStats(SigPlan $plan): SigPlan
    {
        $successful = $plan->installments()->where('status', 'success');

        $withdrawnGrams = (float) MetalWithdrawal::query()
            ->where('sig_plan_id', $plan->id)
            ->where('asset_source', 'sig')
            ->whereIn('status', ['approved', 'paid'])
            ->sum('quantity_grams');

        $investedGrams = (float) (clone $successful)->sum('quantity_grams');

        $plan->update([
            'completed_installments' => (clone $successful)->count(),
            'total_invested' => (clone $successful)->sum('amount'),
            'metal_accumulated_grams' => max(0, round($investedGrams - $withdrawnGrams, 6)),
        ]);

        return $plan->fresh();
    }

    public function recordInstallment(SigPlan $plan, array $data): SigInstallment
    {
        $installment = $plan->installments()->create([
            'user_id' => $plan->user_id,
            'amount' => $data['amount'] ?? $plan->amount,
            'quantity_grams' => $data['quantity_grams'] ?? null,
            'rate_per_gram' => $data['rate_per_gram'] ?? null,
            'status' => $data['status'] ?? 'success',
            'scheduled_at' => $data['scheduled_at'] ?? now(),
            'processed_at' => ($data['status'] ?? 'success') === 'success' ? now() : null,
            'failure_reason' => $data['failure_reason'] ?? null,
            'investment_id' => $data['investment_id'] ?? null,
        ]);

        $this->syncStats($plan);

        if (($data['status'] ?? 'success') === 'success') {
            $user = $plan->user ?? User::query()->find($plan->user_id);
            if ($user) {
                $this->referrals->evaluatePendingBonusAfterCommit($user);
            }
        }

        return $installment;
    }

    public function nextDebitAt(string $frequency, ?Carbon $from = null): Carbon
    {
        $from ??= now();

        return match ($frequency) {
            'daily' => $from->copy()->addDay()->startOfDay()->addHours(9),
            'weekly' => $from->copy()->addWeek()->startOfDay()->addHours(9),
            'monthly' => $from->copy()->addMonth()->startOfDay()->addHours(9),
            default => $from->copy()->addWeek(),
        };
    }

    public function defaultInstallmentCount(string $frequency): int
    {
        return match ($frequency) {
            'daily' => 365,
            'weekly' => 52,
            'monthly' => 12,
            default => 52,
        };
    }

    private function bankLast4(?string $accountNumber): ?string
    {
        if (blank($accountNumber) || strlen($accountNumber) < 4) {
            return null;
        }

        return substr($accountNumber, -4);
    }
}
