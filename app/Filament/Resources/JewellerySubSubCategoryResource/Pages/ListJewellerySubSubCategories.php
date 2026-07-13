<?php

namespace App\Filament\Resources\JewellerySubSubCategoryResource\Pages;

use App\Filament\Resources\JewellerySubSubCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListJewellerySubSubCategories extends ListRecords
{
    protected static string $resource = JewellerySubSubCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
