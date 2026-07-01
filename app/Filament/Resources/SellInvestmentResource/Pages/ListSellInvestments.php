<?php

namespace App\Filament\Resources\SellInvestmentResource\Pages;

use App\Filament\Exports\InvestmentReportExporter;
use App\Filament\Resources\SellInvestmentResource;
use App\Support\FilamentExportActions;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSellInvestments extends ListRecords
{
    protected static string $resource = SellInvestmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            FilamentExportActions::headerExport(InvestmentReportExporter::class, 'sell_transactions'),
            Actions\CreateAction::make(),
        ];
    }
}
