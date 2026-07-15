<?php

namespace App\Console\Commands;

use App\Services\HoldingLotService;
use Illuminate\Console\Command;

class CreditHoldAnniversaryBonuses extends Command
{
    protected $signature = 'holdings:credit-anniversary-bonuses';

    protected $description = 'Credit 1% hold anniversary bonuses for lots held beyond the configured hold period.';

    public function handle(HoldingLotService $lots): int
    {
        $count = $lots->creditAllEligibleBonuses();
        $this->info("Credited hold bonuses for {$count} user(s).");

        return self::SUCCESS;
    }
}
