<?php

namespace App\Support;

use App\Models\InvestmentGoal;
use Carbon\Carbon;

class InvestmentGoalPayload
{
    /**
     * @param  array<string, mixed>  $portfolio
     */
    public static function goal(InvestmentGoal $goal, array $portfolio = []): array
    {
        $targetAmount = (float) ($goal->target_amount ?? 0);
        $rate = (float) ($portfolio['rates'][$goal->metal_type] ?? 0);
        $currentGrams = (float) $goal->current_grams;
        $currentAmount = $rate > 0
            ? round($currentGrams * $rate, 2)
            : 0.0;

        $progressPercent = $targetAmount > 0
            ? min(100, round(($currentAmount / $targetAmount) * 100))
            : 0;

        $targetDate = $goal->target_date ? Carbon::parse($goal->target_date) : null;
        $daysLeft = $targetDate ? max(0, now()->startOfDay()->diffInDays($targetDate, false)) : null;

        return [
            'id' => $goal->id,
            'title' => $goal->title,
            'metal_type' => $goal->metal_type,
            'metal_type_label' => ucfirst($goal->metal_type),
            'target_amount' => round($targetAmount, 2),
            'target_amount_display' => self::inr($targetAmount),
            'current_amount' => $currentAmount,
            'current_amount_display' => self::inr($currentAmount),
            'monthly_contribution' => $goal->monthly_contribution !== null
                ? round((float) $goal->monthly_contribution, 2)
                : null,
            'monthly_contribution_display' => $goal->monthly_contribution !== null
                ? self::inr((float) $goal->monthly_contribution)
                : null,
            'target_grams' => round((float) $goal->target_grams, 4),
            'current_grams' => round($currentGrams, 4),
            'target_date' => $targetDate?->toDateString(),
            'target_date_display' => $targetDate?->format('d M Y'),
            'days_left' => $daysLeft,
            'progress_percent' => $progressPercent,
            'progress_label' => $progressPercent.'% '.ucfirst($goal->metal_type),
            'status' => $goal->status,
            'status_label' => ucfirst($goal->status),
            'created_at' => $goal->created_at?->toIso8601String(),
            'updated_at' => $goal->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  iterable<int, InvestmentGoal>  $goals
     * @return list<array<string, mixed>>
     */
    public static function collection(iterable $goals, array $portfolio = []): array
    {
        $items = [];

        foreach ($goals as $goal) {
            $items[] = self::goal($goal, $portfolio);
        }

        return $items;
    }

    public static function inr(float $amount): string
    {
        return '₹'.number_format($amount, 2);
    }
}
