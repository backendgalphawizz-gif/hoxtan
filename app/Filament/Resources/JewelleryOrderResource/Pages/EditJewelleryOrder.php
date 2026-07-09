<?php

namespace App\Filament\Resources\JewelleryOrderResource\Pages;

use App\Filament\Resources\JewelleryOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditJewelleryOrder extends EditRecord
{
    protected static string $resource = JewelleryOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
        ];
    }
}
