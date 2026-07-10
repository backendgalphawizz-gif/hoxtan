<?php

namespace App\Services;

use App\Models\Investment;
use App\Models\User;

class UserHoldingsService
{
    public function recalculateForUser(int $userId): void
    {
        $user = User::query()->find($userId);

        if (! $user) {
            return;
        }

        $gold = $this->calculateMetalHoldings($userId, 'gold');
        $silver = $this->calculateMetalHoldings($userId, 'silver');

        $user->update([
            'gold_holdings' => $gold,
            'silver_holdings' => $silver,
            'role' => ($gold > 0 || $silver > 0) ? 'investor' : $user->role,
        ]);
    }

    public function calculateMetalHoldings(int $userId, string $metalType): float
    {
        $buyTotal = Investment::query()
            ->where('user_id', $userId)
            ->where('metal_type', $metalType)
            ->where('type', 'buy')
            ->where('status', 'completed')
            ->sum('quantity_grams');

        $sellTotal = Investment::query()
            ->where('user_id', $userId)
            ->where('metal_type', $metalType)
            ->where('type', 'sell')
            ->where('status', 'completed')
            ->sum('quantity_grams');

        return max(0, round((float) $buyTotal - (float) $sellTotal, 4));
    }
}
