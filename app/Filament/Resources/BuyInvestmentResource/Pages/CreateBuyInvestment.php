<?php

namespace App\Filament\Resources\BuyInvestmentResource\Pages;

use App\Filament\Resources\BuyInvestmentResource;
use App\Filament\Resources\InvestmentResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseCreateRecord;

class CreateBuyInvestment extends BaseCreateRecord
{
    protected static string $resource = BuyInvestmentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = 'buy';

        return InvestmentResource::calculateAmounts($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return InvestmentResource::calculateAmounts($data);
    }
}
