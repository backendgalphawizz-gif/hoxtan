<?php

namespace App\Filament\Resources\StaticPageResource\Pages;

use App\Filament\Exports\StaticPageExporter;
use App\Filament\Resources\StaticPageResource;
use App\Support\FilamentExportActions;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStaticPages extends ListRecords
{
    protected static string $resource = StaticPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            FilamentExportActions::headerExport(StaticPageExporter::class, 'static_pages'),
            Actions\CreateAction::make(),
        ];
    }
}
