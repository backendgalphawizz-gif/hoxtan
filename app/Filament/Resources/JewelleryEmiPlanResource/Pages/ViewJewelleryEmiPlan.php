<?php

namespace App\Filament\Resources\JewelleryEmiPlanResource\Pages;

use App\Filament\Resources\JewelleryEmiPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewJewelleryEmiPlan extends ViewRecord
{
    protected static string $resource = JewelleryEmiPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
