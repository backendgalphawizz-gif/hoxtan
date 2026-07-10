<?php

namespace App\Filament\Resources\JewelleryEmiPlanResource\Pages;

use App\Filament\Resources\JewelleryEmiPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListJewelleryEmiPlans extends ListRecords
{
    protected static string $resource = JewelleryEmiPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
