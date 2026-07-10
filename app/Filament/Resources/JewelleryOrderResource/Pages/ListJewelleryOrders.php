<?php

namespace App\Filament\Resources\JewelleryOrderResource\Pages;

use App\Filament\Resources\JewelleryOrderResource;
use App\Models\JewelleryOrderListing;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListJewelleryOrders extends ListRecords
{
    protected static string $resource = JewelleryOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getTableQuery(): Builder
    {
        return JewelleryOrderListing::query()
            ->with(['user', 'driver']);
    }
}
