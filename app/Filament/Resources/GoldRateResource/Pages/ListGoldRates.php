<?php

namespace App\Filament\Resources\GoldRateResource\Pages;

use App\Filament\Resources\GoldRateResource;
use Filament\Resources\Pages\ListRecords;

class ListGoldRates extends ListRecords
{
    protected static string $resource = GoldRateResource::class;

    public function getSubheading(): ?string
    {
        return 'View all synced and manually overridden gold rates.';
    }
}
