<?php

namespace App\Filament\Resources\KycDetailResource\Pages;

use App\Filament\Resources\KycDetailResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;

class EditKycDetail extends BaseEditRecord
{
    protected static string $resource = KycDetailResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\ViewAction::make(), Actions\DeleteAction::make()];
    }
}
