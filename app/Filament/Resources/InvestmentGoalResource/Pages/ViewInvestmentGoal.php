<?php

namespace App\Filament\Resources\InvestmentGoalResource\Pages;

use App\Filament\Resources\InvestmentGoalResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewInvestmentGoal extends ViewRecord
{
    protected static string $resource = InvestmentGoalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
