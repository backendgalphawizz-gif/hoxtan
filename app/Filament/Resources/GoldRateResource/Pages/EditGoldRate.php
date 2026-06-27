<?php

namespace App\Filament\Resources\GoldRateResource\Pages;

use App\Filament\Resources\GoldRateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGoldRate extends EditRecord
{
    protected static string $resource = GoldRateResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
