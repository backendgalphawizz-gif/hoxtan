<?php

namespace App\Filament\Resources\SilverRateResource\Pages;

use App\Filament\Resources\SilverRateResource;
use App\Models\MetalRate;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateSilverRate extends CreateRecord
{
    protected static string $resource = SilverRateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['metal_type'] = 'silver';
        $data['updated_by'] = Auth::guard('admin')->id();

        if ($data['is_active'] ?? false) {
            MetalRate::where('metal_type', 'silver')->update(['is_active' => false]);
        }

        return $data;
    }
}
