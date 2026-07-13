<?php

namespace App\Console\Commands;

use App\Services\MetalRateService;
use App\Support\MetalRateRealtimeConfig;
use Illuminate\Console\Command;

class BroadcastMetalRates extends Command
{
    protected $signature = 'metals:broadcast-rates';

    protected $description = 'Push current gold/silver rates to the WebSocket channel for mobile apps';

    public function handle(MetalRateService $rates): int
    {
        if (! MetalRateRealtimeConfig::isEnabled()) {
            $this->warn('Realtime broadcasting is disabled (set BROADCAST_CONNECTION=reverb).');

            return self::SUCCESS;
        }

        $rates->broadcastCurrentRates();
        $this->info('Metal rates broadcast to channel '.config('metal_rates.broadcast_channel', 'metal-rates'));

        return self::SUCCESS;
    }
}
