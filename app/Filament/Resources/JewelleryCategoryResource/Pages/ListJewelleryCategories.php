<?php

namespace App\Filament\Resources\JewelleryCategoryResource\Pages;

use App\Filament\Resources\JewelleryCategoryResource;
use App\Models\JewelleryCategory;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListJewelleryCategories extends ListRecords
{
    protected static string $resource = JewelleryCategoryResource::class;

    public function mount(): void
    {
        parent::mount();
        JewelleryCategory::ensureSortSequence();
    }

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
