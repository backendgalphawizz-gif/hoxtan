<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('notifications:dispatch-scheduled')->everyMinute();

// Metals-API: only 3 times/day (saves monthly quota). WebSocket uses DB rates only.
Schedule::command('metals:sync-live')->dailyAt('09:00');
Schedule::command('metals:sync-live')->dailyAt('13:00');
Schedule::command('metals:sync-live')->dailyAt('18:00');

// Push stored DB rates to mobile continuously — does NOT call Metals-API.
Schedule::command('metals:broadcast-rates')->everyMinute();
