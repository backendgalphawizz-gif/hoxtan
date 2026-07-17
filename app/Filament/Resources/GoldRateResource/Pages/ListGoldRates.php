<?php

namespace App\Filament\Resources\GoldRateResource\Pages;

use App\Filament\Exports\MetalRateExporter;
use App\Filament\Resources\GoldRateResource;
use App\Support\FilamentExportActions;
use Filament\Resources\Pages\ListRecords;

class ListGoldRates extends ListRecords
{
    protected static string $resource = GoldRateResource::class;

    public function getSubheading(): ?string
    {
        return 'View all synced and manually overridden gold rates.';
    }

    protected function getHeaderActions(): array
    {
        return [
            FilamentExportActions::headerExport(MetalRateExporter::class, 'gold_rate_history'),
            // CreateAction temporarily hidden — re-enable when needed.
        ];
    }
}
