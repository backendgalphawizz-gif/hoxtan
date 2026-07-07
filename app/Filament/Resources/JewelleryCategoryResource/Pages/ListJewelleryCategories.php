<?php

namespace App\Filament\Resources\JewelleryCategoryResource\Pages;

use App\Filament\Resources\JewelleryCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListJewelleryCategories extends ListRecords
{
    protected static string $resource = JewelleryCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
