<?php

namespace App\Filament\Resources\GoldRateResource\Pages;

use App\Filament\Resources\GoldRateResource;
use App\Models\MetalRate;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseCreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateGoldRate extends BaseCreateRecord
{
    protected static string $resource = GoldRateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['metal_type'] = 'gold';
        $data['updated_by'] = Auth::guard('admin')->id();

        if ($data['is_active'] ?? false) {
            MetalRate::where('metal_type', 'gold')->update(['is_active' => false]);
        }

        return $data;
    }
}
