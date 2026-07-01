<?php

namespace App\Filament\Resources\RedemptionResource\Pages;

use App\Filament\Exports\RedemptionExporter;
use App\Filament\Resources\RedemptionResource;
use App\Support\FilamentExportActions;
use Filament\Resources\Pages\ListRecords;

class ListRedemptions extends ListRecords
{
    protected static string $resource = RedemptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            FilamentExportActions::headerExport(RedemptionExporter::class, 'redemption_requests'),
        ];
    }
}
