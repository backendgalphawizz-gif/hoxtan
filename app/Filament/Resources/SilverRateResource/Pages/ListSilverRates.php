<?php

namespace App\Filament\Resources\SilverRateResource\Pages;

use App\Filament\Exports\MetalRateExporter;
use App\Filament\Resources\SilverRateResource;
use App\Support\FilamentExportActions;
use Filament\Resources\Pages\ListRecords;

class ListSilverRates extends ListRecords
{
    protected static string $resource = SilverRateResource::class;

    public function getSubheading(): ?string
    {
        return 'View all synced and manually overridden silver rates.';
    }

    protected function getHeaderActions(): array
    {
        return [
            FilamentExportActions::headerExport(MetalRateExporter::class, 'silver_rate_history'),
            // CreateAction temporarily hidden — re-enable when needed.
        ];
    }
}
