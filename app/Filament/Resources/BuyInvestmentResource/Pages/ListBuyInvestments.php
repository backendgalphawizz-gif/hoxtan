<?php

namespace App\Filament\Resources\BuyInvestmentResource\Pages;

use App\Filament\Resources\BuyInvestmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBuyInvestments extends ListRecords
{
    protected static string $resource = BuyInvestmentResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
