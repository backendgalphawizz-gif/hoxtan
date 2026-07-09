<?php

namespace App\Filament\Resources\JewelleryOrderResource\Pages;

use App\Filament\Resources\JewelleryOrderResource;
use Filament\Resources\Pages\ListRecords;

class ListJewelleryOrders extends ListRecords
{
    protected static string $resource = JewelleryOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
