<?php

namespace App\Filament\Resources\RedemptionResource\Pages;

use App\Filament\Resources\RedemptionResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;

class EditRedemption extends BaseEditRecord
{
    protected static string $resource = RedemptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
