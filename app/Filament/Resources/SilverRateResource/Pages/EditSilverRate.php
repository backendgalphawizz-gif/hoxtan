<?php

namespace App\Filament\Resources\SilverRateResource\Pages;

use App\Filament\Resources\SilverRateResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;

class EditSilverRate extends BaseEditRecord
{
    protected static string $resource = SilverRateResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
