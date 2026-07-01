<?php

namespace App\Filament\Resources\BuyInvestmentResource\Pages;

use App\Filament\Exports\InvestmentReportExporter;
use App\Filament\Resources\BuyInvestmentResource;
use App\Support\FilamentExportActions;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBuyInvestments extends ListRecords
{
    protected static string $resource = BuyInvestmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            FilamentExportActions::headerExport(InvestmentReportExporter::class, 'buy_transactions'),
            Actions\CreateAction::make(),
        ];
    }
}
