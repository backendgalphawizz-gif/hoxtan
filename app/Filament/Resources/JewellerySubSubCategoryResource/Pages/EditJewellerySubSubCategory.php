<?php

namespace App\Filament\Resources\JewellerySubSubCategoryResource\Pages;

use App\Filament\Resources\JewellerySubSubCategoryResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use Filament\Actions;

class EditJewellerySubSubCategory extends BaseEditRecord
{
    protected static string $resource = JewellerySubSubCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
