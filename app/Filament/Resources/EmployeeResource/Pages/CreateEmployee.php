<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use App\Filament\Resources\Pages\BaseCreateRecord;
use App\Models\Employee;
use Illuminate\Support\Facades\Auth;

class CreateEmployee extends BaseCreateRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_admin_id'] = Auth::guard('admin')->id();
        $data['role'] = Employee::ROLE_STAFF;

        return $data;
    }
}
