<?php

namespace App\Filament\Resources\BuyInvestmentResource\Pages;

use App\Filament\Resources\BuyInvestmentResource;
use App\Filament\Resources\InvestmentResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateBuyInvestment extends CreateRecord
{
    protected static string $resource = BuyInvestmentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = 'buy';

        return InvestmentResource::calculateAmounts($data);
    }
}
