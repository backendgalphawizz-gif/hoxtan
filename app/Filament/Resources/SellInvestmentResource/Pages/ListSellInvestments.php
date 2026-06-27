<?php

namespace App\Filament\Resources\SellInvestmentResource\Pages;

use App\Filament\Resources\SellInvestmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSellInvestments extends ListRecords
{
    protected static string $resource = SellInvestmentResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
