<?php

namespace App\Filament\Resources\SellInvestmentResource\Pages;

use App\Filament\Resources\InvestmentResource;
use App\Filament\Resources\SellInvestmentResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSellInvestment extends CreateRecord
{
    protected static string $resource = SellInvestmentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = 'sell';

        return InvestmentResource::calculateAmounts($data);
    }
}
