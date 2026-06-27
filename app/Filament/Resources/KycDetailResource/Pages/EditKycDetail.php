<?php

namespace App\Filament\Resources\KycDetailResource\Pages;

use App\Filament\Resources\KycDetailResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditKycDetail extends EditRecord
{
    protected static string $resource = KycDetailResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\ViewAction::make(), Actions\DeleteAction::make()];
    }
}
