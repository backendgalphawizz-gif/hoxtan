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
     * Reduce remaining_grams on a specific hold lot (no FIFO).
     * When $lotId is null, reduces from each lot only if a single lot matches sell — otherwise requires lotId.
     */
    public function consumeHoldLots(int $userId, string $metalType, float $grams, ?int $lotId = null): void
    {
        $remaining = round($grams, 4);
        if ($remaining <= 0) {
            return;
        }

        DB::transaction(function () use ($userId, $metalType, $lotId, &$remaining): void {
            $query = Investment::query()
                ->where('user_id', $userId)
                ->where('metal_type', $metalType)
                ->where('type', 'buy')
                ->where('status', 'completed')
                ->where('remaining_grams', '>', 0)
                ->lockForUpdate();

            if ($lotId !== null) {
                $query->whereKey($lotId);
            }

            $lots = $query->orderBy('id')->get();

            if ($lotId !== null && $lots->isEmpty()) {
                throw ValidationException::withMessages([
                    'lot_id' => ['Selected holding lot was not found or has no remaining balance.'],
                ]);
            }

            // Without a lot id: only allow when exactly one matching lot exists.
            if ($lotId === null && $lots->count() > 1) {
                throw ValidationException::withMessages([
                    'lot_id' => ['Please select which purchase lot to sell from.'],
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
                    'weight_grams' => ['Not enough remaining balance in the selected lot.'],
                ]);
            }
        });
    }
}
