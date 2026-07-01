<?php

namespace App\Filament\Resources\OfferResource\Pages;

use App\Filament\Exports\OfferExporter;
use App\Filament\Resources\OfferResource;
use App\Support\FilamentExportActions;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOffers extends ListRecords
{
    protected static string $resource = OfferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            FilamentExportActions::headerExport(OfferExporter::class, 'offers'),
            Actions\CreateAction::make(),
        ];
    }
}
