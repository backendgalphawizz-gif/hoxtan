<?php

namespace App\Console\Commands;

use App\Services\MetalRateService;
use Illuminate\Console\Command;

class SyncMetalRatesFromApi extends Command
{
    protected $signature = 'metals:sync-live';

    protected $description = 'Fetch gold/silver from Metals-API once (scheduled 3×/day) and broadcast stored rates';

    public function handle(MetalRateService $rates): int
    {
        if (! config('services.metals_api.key')) {
            $this->error('METALS_API_KEY is not set in .env');

            return self::FAILURE;
        }

        foreach (['gold', 'silver'] as $metal) {
            $record = $rates->syncLiveRate($metal);
            $this->info(ucfirst($metal).': ₹'.number_format((float) $record->rate_per_gram, 2).'/g');
        }

        return self::SUCCESS;
    }
}
