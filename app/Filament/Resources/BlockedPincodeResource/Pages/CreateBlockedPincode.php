<?php

namespace App\Filament\Resources\BlockedPincodeResource\Pages;

use App\Filament\Resources\BlockedPincodeResource;
use App\Filament\Resources\Pages\BaseCreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateBlockedPincode extends BaseCreateRecord
{
    protected static string $resource = BlockedPincodeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::guard('admin')->id();

        return $data;
    }
}
