<?php

use App\Models\Investment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->decimal('remaining_grams', 14, 4)->nullable()->after('quantity_grams');
            $table->timestamp('hold_started_at')->nullable()->after('status');
            $table->timestamp('hold_bonus_credited_at')->nullable()->after('hold_started_at');
            $table->string('purpose', 30)->nullable()->after('hold_bonus_credited_at');
        });

        // Backfill buy lots so each purchase can track hold period + remaining balance.
        Investment::query()
            ->where('type', 'buy')
            ->where('status', 'completed')
            ->orderBy('id')
            ->each(function (Investment $buy): void {
                $buy->forceFill([
                    'remaining_grams' => (float) $buy->quantity_grams,
                    'hold_started_at' => $buy->created_at,
                    'purpose' => filled($buy->purpose) ? $buy->purpose : 'hold',
                ])->saveQuietly();
            });

        // Allocate historical sells FIFO against remaining buy lots.
        $userIds = Investment::query()
            ->where('status', 'completed')
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            foreach (['gold', 'silver'] as $metal) {
                $sells = Investment::query()
                    ->where('user_id', $userId)
                    ->where('metal_type', $metal)
                    ->where('type', 'sell')
                    ->where('status', 'completed')
                    ->orderBy('id')
                    ->get(['quantity_grams']);

                $remainingToAllocate = round((float) $sells->sum('quantity_grams'), 4);
                if ($remainingToAllocate <= 0) {
                    continue;
                }

                $lots = Investment::query()
                    ->where('user_id', $userId)
                    ->where('metal_type', $metal)
                    ->where('type', 'buy')
                    ->where('status', 'completed')
                    ->orderBy('hold_started_at')
                    ->orderBy('id')
                    ->get();

                foreach ($lots as $lot) {
                    if ($remainingToAllocate <= 0) {
                        break;
                    }

                    $lotRemaining = round((float) ($lot->remaining_grams ?? $lot->quantity_grams), 4);
                    $take = min($lotRemaining, $remainingToAllocate);
                    $lot->forceFill([
                        'remaining_grams' => max(0, round($lotRemaining - $take, 4)),
                    ])->saveQuietly();
                    $remainingToAllocate = round($remainingToAllocate - $take, 4);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->dropColumn([
                'remaining_grams',
                'hold_started_at',
                'hold_bonus_credited_at',
                'purpose',
            ]);
        });
    }
};
