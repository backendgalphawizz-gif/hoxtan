<?php

namespace App\Filament\Resources\InvestmentGoalResource\Pages;

use App\Filament\Resources\InvestmentGoalResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInvestmentGoals extends ListRecords
{
    protected static string $resource = InvestmentGoalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
