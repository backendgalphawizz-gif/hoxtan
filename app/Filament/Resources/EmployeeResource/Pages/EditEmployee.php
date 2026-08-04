<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Models\Employee;
use Filament\Actions;

class EditEmployee extends BaseEditRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
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
