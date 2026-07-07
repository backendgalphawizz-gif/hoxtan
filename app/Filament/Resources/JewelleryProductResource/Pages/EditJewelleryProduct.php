<?php

namespace App\Filament\Resources\JewelleryProductResource\Pages;

use App\Filament\Resources\JewelleryProductResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Support\JewelleryPricing;
use Filament\Actions;

class EditJewelleryProduct extends BaseEditRecord
{
    protected static string $resource = JewelleryProductResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $pricing = JewelleryPricing::calculate(
            $data['metal_type'] ?? null,
            $data['weight_grams'] ?? null,
            $data['making_charge_percent'] ?? null,
        );

        $data['price'] = $pricing['total'];

        return $data;
    }
}
