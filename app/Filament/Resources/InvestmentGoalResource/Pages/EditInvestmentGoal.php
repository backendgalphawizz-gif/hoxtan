<?php

namespace App\Filament\Resources\InvestmentGoalResource\Pages;

use App\Filament\Resources\InvestmentGoalResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;

class EditInvestmentGoal extends BaseEditRecord
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
