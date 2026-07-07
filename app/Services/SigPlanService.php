<?php

namespace App\Services;

use App\Models\SigInstallment;
use App\Models\SigPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SigPlanService
{
    public function activate(array $data, ?int $adminId = null): SigPlan
    {
        return DB::transaction(function () use ($data, $adminId): SigPlan {
            /** @var User $user */
            $user = User::query()->with('kycDetail')->findOrFail($data['user_id']);

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

            return $plan->fresh(['user']);
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

        $plan->update([
            'completed_installments' => (clone $successful)->count(),
            'total_invested' => (clone $successful)->sum('amount'),
            'metal_accumulated_grams' => (clone $successful)->sum('quantity_grams'),
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
