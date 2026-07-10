<?php

namespace App\Filament\Resources\DriverResource\Pages;

use App\Filament\Resources\DriverResource;
use App\Filament\Resources\Pages\BaseCreateRecord;

class CreateDriver extends BaseCreateRecord
{
    protected static string $resource = DriverResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['is_active'] = $data['is_active'] ?? true;
        $data['is_online'] = $data['is_online'] ?? true;

        return $data;
    }
}
