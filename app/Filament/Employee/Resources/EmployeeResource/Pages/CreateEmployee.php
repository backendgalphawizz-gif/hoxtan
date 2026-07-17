<?php

namespace App\Filament\Employee\Resources\EmployeeResource\Pages;

use App\Filament\Employee\Resources\EmployeeResource;
use App\Models\Employee;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        /** @var Employee $creator */
        $creator = Auth::guard('employee')->user();

        $data['created_by_employee_id'] = $creator->id;
        $data['department_id'] = $creator->department_id;

        return $data;
    }
}
