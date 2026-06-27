<?php

namespace App\Filament\Resources\SilverRateResource\Pages;

use App\Filament\Resources\SilverRateResource;
use Filament\Resources\Pages\ListRecords;

class ListSilverRates extends ListRecords
{
    protected static string $resource = SilverRateResource::class;

    public function getSubheading(): ?string
    {
        return 'View all synced and manually overridden silver rates.';
    }
}
