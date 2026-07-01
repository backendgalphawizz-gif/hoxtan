<?php

namespace App\Filament\Resources\SellInvestmentResource\Pages;

use App\Filament\Resources\SellInvestmentResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;

class EditSellInvestment extends BaseEditRecord
{
    protected static string $resource = SellInvestmentResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\ViewAction::make(), Actions\DeleteAction::make()];
    }
}
