<?php

namespace App\Filament\Resources\KycDetailResource\Pages;

use App\Filament\Resources\KycDetailResource;
use App\Filament\Resources\Pages\BaseCreateRecord;

class CreateKycDetail extends BaseCreateRecord
{
    protected static string $resource = KycDetailResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['submitted_at'] = now();

        return $data;
    }
}
