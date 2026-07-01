<?php

namespace App\Filament\Resources\BannerResource\Pages;

use App\Filament\Exports\BannerExporter;
use App\Filament\Resources\BannerResource;
use App\Support\FilamentExportActions;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBanners extends ListRecords
{
    protected static string $resource = BannerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            FilamentExportActions::headerExport(BannerExporter::class, 'banners'),
            Actions\CreateAction::make(),
        ];
    }
}
