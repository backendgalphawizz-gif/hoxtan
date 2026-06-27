<?php

namespace App\Filament\Resources\SellInvestmentResource\Pages;

use App\Filament\Resources\SellInvestmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSellInvestment extends EditRecord
{
    protected static string $resource = SellInvestmentResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\ViewAction::make(), Actions\DeleteAction::make()];
    }
}
