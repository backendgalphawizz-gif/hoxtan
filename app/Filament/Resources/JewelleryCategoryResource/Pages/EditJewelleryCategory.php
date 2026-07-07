<?php

namespace App\Filament\Resources\JewelleryCategoryResource\Pages;

use App\Filament\Resources\JewelleryCategoryResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use Filament\Actions;

class EditJewelleryCategory extends BaseEditRecord
{
    protected static string $resource = JewelleryCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
