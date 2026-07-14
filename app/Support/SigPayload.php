<?php

namespace App\Support;

use App\Models\SigInstallment;
use App\Models\SigPlan;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SigPayload
{
    public static function plan(SigPlan $plan, bool $includeManageActions = true): array
    {
        $plan->loadMissing('installments');

        $payload = [
            'id' => $plan->id,
            'plan_number' => $plan->plan_number,
            'title' => self::planTitle($plan),
            'metal_type' => $plan->metal_type,
            'metal_type_label' => ucfirst($plan->metal_type),
            'frequency' => $plan->frequency,
            'frequency_label' => ucfirst($plan->frequency),
            'amount' => (float) $plan->amount,
            'amount_display' => self::amountWithFrequency($plan),
            'status' => $plan->status,
            'status_label' => self::statusLabel($plan->status),
            'linked_bank' => $plan->linked_bank_label,
            'linked_bank_name' => $plan->linked_bank_name,
            'linked_bank_last4' => $plan->linked_bank_last4,
            'next_auto_debit_at' => $plan->next_debit_at?->toIso8601String(),
            'next_auto_debit_display' => $plan->next_debit_at?->format('d F Y'),
            'total_invested' => (float) $plan->total_invested,
            'total_invested_display' => '₹'.number_format((float) $plan->total_invested, 0),
            'metal_accumulated_grams' => (float) $plan->metal_accumulated_grams,
            'metal_accumulated_display' => rtrim(rtrim(number_format((float) $plan->metal_accumulated_grams, 6, '.', ''), '0'), '.').'g',
            'completed_installments' => (int) $plan->completed_installments,
            'total_installments' => $plan->total_installments !== null ? (int) $plan->total_installments : null,
            'progress_label' => $plan->progress_label,
            'progress_percent' => self::progressPercent($plan),
            'activated_at' => $plan->activated_at?->toIso8601String(),
            'activated_at_display' => $plan->activated_at?->format('d F Y'),
            'paused_at' => $plan->paused_at?->toIso8601String(),
            'stopped_at' => $plan->stopped_at?->toIso8601String(),
        ];

        if ($includeManageActions) {
            $payload['manage_actions'] = self::manageActions($plan->status);
        }

        return $payload;
    }

    public static function installment(SigInstallment $installment): array
    {
        $installment->loadMissing('plan');

        $plan = $installment->plan;
        $processedAt = $installment->processed_at ?? $installment->scheduled_at;

        return [
            'id' => $installment->id,
            'reference_id' => $installment->reference_id,
            'title' => $plan ? self::transactionTitle($plan) : 'SIG Transaction',
            'metal_type' => $plan?->metal_type,
            'frequency' => $plan?->frequency,
            'amount' => (float) $installment->amount,
            'amount_display' => '₹'.number_format((float) $installment->amount, 2),
            'quantity_grams' => $installment->quantity_grams !== null
                ? round((float) $installment->quantity_grams, 6)
                : null,
            'quantity_display' => $installment->quantity_grams !== null
                ? rtrim(rtrim(number_format((float) $installment->quantity_grams, 6, '.', ''), '0'), '.').' g'
                : null,
            'rate_per_gram' => $installment->rate_per_gram !== null
                ? round((float) $installment->rate_per_gram, 2)
                : null,
            'status' => $installment->status,
            'status_label' => self::installmentStatusLabel($installment->status),
            'scheduled_at' => $installment->scheduled_at?->toIso8601String(),
            'processed_at' => $installment->processed_at?->toIso8601String(),
            'time_display' => $processedAt
                ? $processedAt->format('H:i').' • '.$processedAt->format('d F Y')
                : null,
        ];
    }

    /**
     * @param  Collection<int, SigInstallment>  $installments
     */
    public static function installmentCollection(Collection $installments): array
    {
        return $installments
            ->map(fn (SigInstallment $installment) => self::installment($installment))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function manageActions(string $status): array
    {
        return collect(config('sig.manage_actions', []))
            ->filter(fn (array $action): bool => in_array($status, $action['available_when'] ?? [], true))
            ->values()
            ->all();
    }

    public static function planTitle(SigPlan $plan): string
    {
        return ucfirst($plan->frequency).' '.strtoupper($plan->metal_type).' SIG';
    }

    public static function transactionTitle(SigPlan $plan): string
    {
        return ucfirst($plan->metal_type).' SIG ('.ucfirst($plan->frequency).')';
    }

    public static function amountWithFrequency(SigPlan $plan): string
    {
        $suffix = match ($plan->frequency) {
            'daily' => '/day',
            'weekly' => '/week',
            'monthly' => '/month',
            default => '',
        };

        return '₹'.number_format((float) $plan->amount, 0).$suffix;
    }

    public static function statusLabel(?string $status): string
    {
        return config('sig.statuses.'.$status, Str::headline((string) $status));
    }

    public static function installmentStatusLabel(?string $status): string
    {
        return config('sig.installment_statuses.'.$status, Str::upper((string) $status));
    }

    public static function progressPercent(SigPlan $plan): ?float
    {
        if (! $plan->total_installments) {
            return null;
        }

        if ($plan->total_installments <= 0) {
            return 0;
        }

        return round(((int) $plan->completed_installments / (int) $plan->total_installments) * 100, 2);
    }
}
