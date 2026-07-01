<?php

namespace App\Filament\Resources\BuyInvestmentResource\Pages;

use App\Filament\Resources\BuyInvestmentResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;

class EditBuyInvestment extends BaseEditRecord
{
    protected static string $resource = BuyInvestmentResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\ViewAction::make(), Actions\DeleteAction::make()];
    }
}
