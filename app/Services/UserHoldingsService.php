<?php

namespace App\Services;

use App\Models\Investment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

    /**
     * Grams that can be sold (remaining lots older than $minAgeHours).
     */
    public function sellableGrams(int $userId, string $metalType, int $minAgeHours = 48): float
    {
        $cutoff = now()->subHours(max(0, $minAgeHours));

        $grams = Investment::query()
            ->where('user_id', $userId)
            ->where('metal_type', $metalType)
            ->where('type', 'buy')
            ->where('status', 'completed')
            ->where('remaining_grams', '>', 0)
            ->where(function ($q) use ($cutoff): void {
                $q->where('hold_started_at', '<=', $cutoff)
                    ->orWhere(function ($inner) use ($cutoff): void {
                        $inner->whereNull('hold_started_at')
                            ->where('created_at', '<=', $cutoff);
                    });
            })
            ->sum('remaining_grams');

        return max(0, round((float) $grams, 4));
    }

    /**
     * Reduce remaining_grams on hold lots.
     * - With $lotId: sell from that lot only.
     * - Without $lotId: consume unlocked lots (age >= $minAgeHours) until grams covered.
     */
    public function consumeHoldLots(
        int $userId,
        string $metalType,
        float $grams,
        ?int $lotId = null,
        ?int $minAgeHours = null,
    ): void {
        $remaining = round($grams, 4);
        if ($remaining <= 0) {
            return;
        }

        DB::transaction(function () use ($userId, $metalType, $lotId, $minAgeHours, &$remaining): void {
            $query = Investment::query()
                ->where('user_id', $userId)
                ->where('metal_type', $metalType)
                ->where('type', 'buy')
                ->where('status', 'completed')
                ->where('remaining_grams', '>', 0)
                ->lockForUpdate();

            if ($lotId !== null) {
                $query->whereKey($lotId);
            } elseif ($minAgeHours !== null) {
                $cutoff = now()->subHours(max(0, $minAgeHours));
                $query->where(function ($q) use ($cutoff): void {
                    $q->where('hold_started_at', '<=', $cutoff)
                        ->orWhere(function ($inner) use ($cutoff): void {
                            $inner->whereNull('hold_started_at')
                                ->where('created_at', '<=', $cutoff);
                        });
                });
            }

            $lots = $query->orderBy('id')->get();

            if ($lotId !== null && $lots->isEmpty()) {
                throw ValidationException::withMessages([
                    'lot_id' => ['Selected holding lot was not found or has no remaining balance.'],
                ]);
            }

            foreach ($lots as $lot) {
                if ($remaining <= 0) {
                    break;
                }

                $lotRemaining = round((float) $lot->remaining_grams, 4);
                if ($lotId !== null && $lotRemaining + 0.00005 < $remaining) {
                    throw ValidationException::withMessages([
                        'weight_grams' => [
                            'Selected lot only has '.number_format($lotRemaining, 4).' g remaining.',
                        ],
                    ]);
                }

                $take = min($lotRemaining, $remaining);
                $lot->forceFill([
                    'remaining_grams' => max(0, round($lotRemaining - $take, 4)),
                ])->save();
                $remaining = round($remaining - $take, 4);
            }

            if ($remaining > 0.00005) {
                throw ValidationException::withMessages([
                    'weight_grams' => ['Not enough sellable holding balance (after lock period).'],
                ]);
            }
        });
    }
}
