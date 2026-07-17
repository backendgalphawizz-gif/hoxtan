<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use App\Filament\Resources\Pages\BaseCreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateEmployee extends BaseCreateRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_admin_id'] = Auth::guard('admin')->id();

        return $data;
    }
}
