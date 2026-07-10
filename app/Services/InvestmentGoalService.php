<?php

namespace App\Services;

use App\Models\InvestmentGoal;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class InvestmentGoalService
{
    public function __construct(
        protected UserHoldingsService $holdings,
        protected MetalRateService $metalRates,
    ) {}

    /**
     * @return array{
     *     rates: array{gold: float, silver: float},
     *     gold_holdings_grams: float,
     *     silver_holdings_grams: float,
     *     gold_value: float,
     *     silver_value: float,
     *     total_value: float,
     *     note: string
     * }
     */
    public function portfolioSummary(User $user): array
    {
        $rates = [
            'gold' => $this->metalRates->getCurrentRatePerGram('gold'),
            'silver' => $this->metalRates->getCurrentRatePerGram('silver'),
        ];

        $goldGrams = (float) $user->gold_holdings;
        $silverGrams = (float) $user->silver_holdings;
        $goldValue = round($goldGrams * $rates['gold'], 2);
        $silverValue = round($silverGrams * $rates['silver'], 2);

        return [
            'rates' => $rates,
            'gold_holdings_grams' => round($goldGrams, 4),
            'silver_holdings_grams' => round($silverGrams, 4),
            'gold_value' => $goldValue,
            'silver_value' => $silverValue,
            'total_value' => round($goldValue + $silverValue, 2),
            'note' => (string) config('goals.portfolio_note'),
        ];
    }

    public function syncUserGoals(User $user): void
    {
        $this->holdings->recalculateForUser($user->id);
        $user->refresh();

        foreach (['gold', 'silver'] as $metalType) {
            $this->allocateHoldingsToGoals($user, $metalType);
        }
    }

    /**
     * @return array{
     *     summary: array<string, mixed>,
     *     portfolio: array<string, mixed>,
     *     goals: list<array<string, mixed>>
     * }
     */
    public function listForUser(User $user, ?string $status = 'active'): array
    {
        $this->syncUserGoals($user);

        $portfolio = $this->portfolioSummary($user->fresh() ?? $user);

        $query = InvestmentGoal::query()
            ->where('user_id', $user->id)
            ->orderBy('created_at');

        if (filled($status)) {
            $query->where('status', $status);
        }

        $goals = $query->get();
        $goalPayloads = $this->mapGoals($goals, $portfolio);

        $activeCount = InvestmentGoal::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->count();

        $completedCount = InvestmentGoal::query()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->count();

        $totalGoalsValue = collect($goalPayloads)->sum('current_amount');

        return [
            'summary' => [
                'total_goals_value' => round($totalGoalsValue, 2),
                'total_goals_value_display' => '₹'.number_format($totalGoalsValue, 2),
                'total_goals' => $activeCount + $completedCount,
                'active_count' => $activeCount,
                'completed_count' => $completedCount,
            ],
            'portfolio' => $portfolio,
            'goals' => $goalPayloads,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createForUser(User $user, array $data): InvestmentGoal
    {
        $metalType = $data['metal_type'] ?? 'gold';
        $rate = $this->metalRates->getCurrentRatePerGram($metalType);
        $targetAmount = (float) $data['target_amount'];
        $targetGrams = $rate > 0 ? round($targetAmount / $rate, 4) : 0.0;

        $goal = InvestmentGoal::query()->create([
            'user_id' => $user->id,
            'title' => $data['title'],
            'metal_type' => $metalType,
            'target_amount' => $targetAmount,
            'monthly_contribution' => isset($data['monthly_contribution'])
                ? (float) $data['monthly_contribution']
                : null,
            'target_grams' => $targetGrams,
            'current_grams' => 0,
            'target_date' => $data['target_date'],
            'status' => 'active',
            'admin_created' => false,
        ]);

        $this->syncUserGoals($user);

        return $goal->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateGoal(InvestmentGoal $goal, array $data): InvestmentGoal
    {
        if ($goal->status === 'completed') {
            throw ValidationException::withMessages([
                'goal' => ['Completed goals cannot be edited.'],
            ]);
        }

        $metalType = $data['metal_type'] ?? $goal->metal_type;
        $rate = $this->metalRates->getCurrentRatePerGram($metalType);
        $targetAmount = (float) $data['target_amount'];
        $targetGrams = $rate > 0 ? round($targetAmount / $rate, 4) : (float) $goal->target_grams;

        $goal->update([
            'title' => $data['title'],
            'metal_type' => $metalType,
            'target_amount' => $targetAmount,
            'target_grams' => $targetGrams,
            'target_date' => $data['target_date'],
            ...array_key_exists('monthly_contribution', $data)
                ? ['monthly_contribution' => $data['monthly_contribution'] !== null
                    ? (float) $data['monthly_contribution']
                    : null]
                : [],
        ]);

        $this->syncUserGoals($goal->user);

        return $goal->fresh();
    }

    public function deleteGoal(InvestmentGoal $goal): void
    {
        if ($goal->status === 'completed') {
            throw ValidationException::withMessages([
                'goal' => ['Completed goals cannot be deleted.'],
            ]);
        }

        $userId = (int) $goal->user_id;
        $goal->delete();

        $user = User::query()->find($userId);

        if ($user) {
            $this->syncUserGoals($user);
        }
    }

    protected function allocateHoldingsToGoals(User $user, string $metalType): void
    {
        $holdings = $metalType === 'gold'
            ? (float) $user->gold_holdings
            : (float) $user->silver_holdings;

        $remaining = $holdings;

        $activeGoals = InvestmentGoal::query()
            ->where('user_id', $user->id)
            ->where('metal_type', $metalType)
            ->where('status', 'active')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        foreach ($activeGoals as $goal) {
            $allocated = min($remaining, (float) $goal->target_grams);
            $remaining = max(0, round($remaining - $allocated, 4));

            $rate = $this->metalRates->getCurrentRatePerGram($metalType);
            $currentAmount = $rate > 0 ? round($allocated * $rate, 2) : 0.0;
            $targetAmount = (float) ($goal->target_amount ?? 0);
            $isCompleted = $allocated >= (float) $goal->target_grams
                || ($targetAmount > 0 && $currentAmount >= $targetAmount);

            $goal->update([
                'current_grams' => $allocated,
                'status' => $isCompleted ? 'completed' : 'active',
            ]);
        }
    }

    /**
     * @param  Collection<int, InvestmentGoal>  $goals
     * @return list<array<string, mixed>>
     */
    protected function mapGoals(Collection $goals, array $portfolio): array
    {
        return $goals
            ->map(fn (InvestmentGoal $goal): array => \App\Support\InvestmentGoalPayload::goal($goal, $portfolio))
            ->values()
            ->all();
    }
}
