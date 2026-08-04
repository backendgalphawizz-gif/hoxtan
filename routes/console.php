<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('notifications:dispatch-scheduled')->everyMinute();

// Metals-API: only 3 times/day IST (saves monthly quota). WebSocket uses DB rates only.
Schedule::command('metals:sync-live')->dailyAt('09:00')->timezone('Asia/Kolkata');
Schedule::command('metals:sync-live')->dailyAt('13:00')->timezone('Asia/Kolkata');
Schedule::command('metals:sync-live')->dailyAt('18:00')->timezone('Asia/Kolkata');

// Push stored DB rates to mobile continuously — does NOT call Metals-API.
// Instant snapshot: mobile calls POST /api/v1/rates/push right after subscribe.
Schedule::command('metals:broadcast-rates')->everyThirtySeconds();

// EMI jewellery cancel refunds: auto-approve if admin does not act within 2 hours.
Schedule::command('jewellery:auto-approve-emi-refunds')->everyMinute();

// EMI payment reminders: from N days before due date, then daily until paid.
Schedule::command('jewellery:send-emi-reminders')
    ->dailyAt((string) config('emi_reminders.schedule_at', '09:00'))
    ->timezone('Asia/Kolkata');

// Metal cash withdrawals: auto-approve if admin does not act within SLA (default 2 hours).
Schedule::command('withdrawals:auto-approve')->everyMinute();

// Holdings: credit 1% anniversary bonus after 1 year hold (per purchase lot).
Schedule::command('holdings:credit-anniversary-bonuses')
    ->dailyAt('01:15')
    ->timezone('Asia/Kolkata');
