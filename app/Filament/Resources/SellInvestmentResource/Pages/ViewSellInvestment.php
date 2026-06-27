<?php

namespace App\Filament\Resources\SellInvestmentResource\Pages;

use App\Filament\Resources\SellInvestmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSellInvestment extends ViewRecord
{
    protected static string $resource = SellInvestmentResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\EditAction::make()];
    }
}
