<?php

namespace App\Filament\Employee\Resources\EmployeeResource\Pages;

use App\Filament\Employee\Resources\EmployeeResource;
use App\Models\Employee;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        unset($data['password'], $data['password_plain']);

        /** @var Employee $record */
        $record = $this->getRecord();
        $data['password'] = $record->readablePassword();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Role is fixed for subordinates created by staff.
        unset($data['role'], $data['role_display'], $data['department_id'], $data['created_by_employee_id']);

        if (! filled($data['password'] ?? null)) {
            unset($data['password'], $data['password_plain']);

            return $data;
        }

        $plain = (string) $data['password'];
        $data['password'] = $plain;
        $data['password_plain'] = encrypt($plain);

        return $data;
    }
}
