<?php

namespace App\Console\Commands;

use App\Services\MetalWithdrawalService;
use Illuminate\Console\Command;

class AutoApproveMetalWithdrawals extends Command
{
    protected $signature = 'withdrawals:auto-approve';

    protected $description = 'Auto-approve pending metal withdrawals older than the configured SLA';

    public function handle(MetalWithdrawalService $withdrawals): int
    {
        $count = $withdrawals->autoApproveExpired();

        $this->info("Auto-approved {$count} metal withdrawal(s).");

        return self::SUCCESS;
    }
}
