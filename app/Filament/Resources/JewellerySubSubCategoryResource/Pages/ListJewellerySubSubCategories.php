<?php

namespace App\Filament\Resources\JewellerySubSubCategoryResource\Pages;

use App\Filament\Resources\JewellerySubSubCategoryResource;
use App\Models\JewellerySubSubCategory;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListJewellerySubSubCategories extends ListRecords
{
    protected static string $resource = JewellerySubSubCategoryResource::class;

    public function mount(): void
    {
        parent::mount();
        JewellerySubSubCategory::ensureSortSequence();
    }

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
