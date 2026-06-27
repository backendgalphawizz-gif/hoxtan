<?php

namespace App\Filament\Resources\InvestmentGoalResource\Pages;

use App\Filament\Resources\InvestmentGoalResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInvestmentGoal extends EditRecord
{
    protected static string $resource = InvestmentGoalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
