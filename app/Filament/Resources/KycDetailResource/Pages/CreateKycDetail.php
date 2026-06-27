<?php

namespace App\Filament\Resources\KycDetailResource\Pages;

use App\Filament\Resources\KycDetailResource;
use Filament\Resources\Pages\CreateRecord;

class CreateKycDetail extends CreateRecord
{
    protected static string $resource = KycDetailResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['submitted_at'] = now();

        return $data;
    }
}
