<?php

namespace App\Filament\Resources\JewellerySubCategoryResource\Pages;

use App\Filament\Resources\JewellerySubCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListJewellerySubCategories extends ListRecords
{
    protected static string $resource = JewellerySubCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
