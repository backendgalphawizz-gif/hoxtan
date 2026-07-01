<?php

namespace App\Filament\Resources\SellInvestmentResource\Pages;

use App\Filament\Resources\InvestmentResource;
use App\Filament\Resources\SellInvestmentResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseCreateRecord;

class CreateSellInvestment extends BaseCreateRecord
{
    protected static string $resource = SellInvestmentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = 'sell';

        return InvestmentResource::calculateAmounts($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return InvestmentResource::calculateAmounts($data);
    }
}
