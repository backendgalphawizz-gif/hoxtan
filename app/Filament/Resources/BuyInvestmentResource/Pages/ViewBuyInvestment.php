<?php

namespace App\Filament\Resources\BuyInvestmentResource\Pages;

use App\Filament\Resources\BuyInvestmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBuyInvestment extends ViewRecord
{
    protected static string $resource = BuyInvestmentResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\EditAction::make()];
    }
}
