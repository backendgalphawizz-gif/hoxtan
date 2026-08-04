<?php

namespace App\Filament\Employee\Resources\UserResource\Pages;

use App\Filament\Employee\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (blank($data['mpin'] ?? null)) {
            unset($data['mpin']);
        }

        unset($data['created_by_employee_id'], $data['created_by_employee_code']);

        return $data;
    }
}
