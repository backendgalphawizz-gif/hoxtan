<?php

namespace App\Filament\Resources\SilverRateResource\Pages;

use App\Filament\Resources\SilverRateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSilverRate extends EditRecord
{
    protected static string $resource = SilverRateResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
