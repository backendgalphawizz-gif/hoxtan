<?php

namespace App\Filament\Resources\FaqResource\Pages;

use App\Filament\Exports\FaqExporter;
use App\Filament\Resources\FaqResource;
use App\Support\FilamentExportActions;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFaqs extends ListRecords
{
    protected static string $resource = FaqResource::class;

    protected function getHeaderActions(): array
    {
        return [
            FilamentExportActions::headerExport(FaqExporter::class, 'faqs'),
            Actions\CreateAction::make(),
        ];
    }
}
