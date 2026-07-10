<?php

namespace App\Observers;

use App\Models\Investment;
use App\Models\User;
use App\Services\InvestmentGoalService;
use App\Services\InvoiceService;

class InvestmentObserver
{
    public function __construct(
        protected InvestmentGoalService $goals,
        protected InvoiceService $invoices,
    ) {}

    public function saved(Investment $investment): void
    {
        if ($investment->wasChanged('status') || $investment->wasRecentlyCreated) {
            $user = User::query()->find($investment->user_id);

            if ($user) {
                $this->goals->syncUserGoals($user);
            }
        }

        if ($investment->wasChanged('status')
            && $investment->status === 'completed'
            && $investment->type === 'buy') {
            $this->invoices->generateForInvestment($investment);
        }
    }

    public function deleted(Investment $investment): void
    {
        $user = User::query()->find($investment->user_id);

        if ($user) {
            $this->goals->syncUserGoals($user);
        }
    }
}
