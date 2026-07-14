<?php

namespace App\Console\Commands;

use App\Services\JewelleryEmiCancellationService;
use Illuminate\Console\Command;

class AutoApproveJewelleryEmiRefunds extends Command
{
    protected $signature = 'jewellery:auto-approve-emi-refunds';

    protected $description = 'Auto-approve pending EMI jewellery refund requests older than 2 hours';

    public function handle(JewelleryEmiCancellationService $cancellation): int
    {
        $count = $cancellation->autoApproveExpired();

        $this->info("Auto-approved {$count} EMI refund request(s).");

        return self::SUCCESS;
    }
}
