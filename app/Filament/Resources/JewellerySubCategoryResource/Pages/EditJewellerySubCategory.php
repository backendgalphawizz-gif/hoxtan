<?php

namespace App\Filament\Resources\JewellerySubCategoryResource\Pages;

use App\Filament\Resources\JewellerySubCategoryResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use Filament\Actions;

class EditJewellerySubCategory extends BaseEditRecord
{
    protected static string $resource = JewellerySubCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
