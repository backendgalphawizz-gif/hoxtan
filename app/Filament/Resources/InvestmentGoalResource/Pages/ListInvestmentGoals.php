<?php

namespace App\Filament\Resources\InvestmentGoalResource\Pages;

use App\Filament\Exports\InvestmentGoalExporter;
use App\Filament\Resources\InvestmentGoalResource;
use App\Support\FilamentExportActions;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInvestmentGoals extends ListRecords
{
    protected static string $resource = InvestmentGoalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            FilamentExportActions::headerExport(InvestmentGoalExporter::class, 'investment_goals'),
            Actions\CreateAction::make(),
        ];
    }
}
