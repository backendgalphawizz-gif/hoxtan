<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\UserHoldingsService;
use Illuminate\Console\Command;

class RecalculateUserHoldings extends Command
{
    protected $signature = 'holdings:recalculate {--user= : Specific user ID}';

    protected $description = 'Recalculate gold/silver holdings from completed transactions';

    public function handle(UserHoldingsService $holdings): int
    {
        $userId = $this->option('user');

        if ($userId) {
            $holdings->recalculateForUser((int) $userId);
            $this->info("Holdings recalculated for user #{$userId}.");

            return self::SUCCESS;
        }

        User::query()->pluck('id')->each(function (int $id) use ($holdings): void {
            $holdings->recalculateForUser($id);
        });

        $this->info('Holdings recalculated for all users.');

        return self::SUCCESS;
    }
}
