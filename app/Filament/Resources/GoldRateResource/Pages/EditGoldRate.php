<?php

namespace App\Filament\Resources\GoldRateResource\Pages;

use App\Filament\Resources\GoldRateResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;

class EditGoldRate extends BaseEditRecord
{
    protected static string $resource = GoldRateResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
