<?php

namespace App\Filament\Resources\DailyReportResource\Pages;

use App\Filament\Exports\DailyReportExporter;
use App\Filament\Resources\DailyReportResource;
use App\Support\FilamentExportActions;
use Filament\Resources\Pages\ListRecords;

class ListDailyReports extends ListRecords
{
    protected static string $resource = DailyReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            FilamentExportActions::headerExport(DailyReportExporter::class, 'daily_reports'),
        ];
    }
}
