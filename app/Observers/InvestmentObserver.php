<?php

namespace App\Observers;

use App\Models\Investment;
use App\Services\InvoiceService;
use App\Services\UserHoldingsService;

class InvestmentObserver
{
    public function __construct(
        protected UserHoldingsService $holdings,
        protected InvoiceService $invoices,
    ) {}

    public function saved(Investment $investment): void
    {
        if ($investment->wasChanged('status') || $investment->wasRecentlyCreated) {
            $this->holdings->recalculateForUser((int) $investment->user_id);
        }

        if ($investment->wasChanged('status')
            && $investment->status === 'completed'
            && $investment->type === 'buy') {
            $this->invoices->generateForInvestment($investment);
        }
    }

    public function deleted(Investment $investment): void
    {
        $this->holdings->recalculateForUser((int) $investment->user_id);
    }
}
