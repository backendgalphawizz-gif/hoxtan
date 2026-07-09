<?php

namespace App\Filament\Resources\BlockedPincodeResource\Pages;

use App\Filament\Resources\BlockedPincodeResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use Filament\Actions;

class EditBlockedPincode extends BaseEditRecord
{
    protected static string $resource = BlockedPincodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
