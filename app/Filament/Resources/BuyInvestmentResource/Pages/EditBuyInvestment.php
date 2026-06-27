<?php

namespace App\Filament\Resources\BuyInvestmentResource\Pages;

use App\Filament\Resources\BuyInvestmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBuyInvestment extends EditRecord
{
    protected static string $resource = BuyInvestmentResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\ViewAction::make(), Actions\DeleteAction::make()];
    }
}
