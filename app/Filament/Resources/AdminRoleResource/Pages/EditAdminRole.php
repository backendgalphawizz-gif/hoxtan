<?php

namespace App\Filament\Resources\AdminRoleResource\Pages;

use App\Filament\Resources\AdminRoleResource;
use App\Support\AdminPermissions;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;

class EditAdminRole extends BaseEditRecord
{
    protected static string $resource = AdminRoleResource::class;

    public function selectAllPermissions(): void
    {
        $this->data['permissions'] = AdminPermissions::allGranted();
    }

    public function clearAllPermissions(): void
    {
        $this->data['permissions'] = AdminPermissions::emptyMatrix();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn (): bool => ! $this->record->is_super_admin),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['permissions'] = AdminPermissions::normalize($data['permissions'] ?? []);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['permissions'] = AdminPermissions::normalize($data['permissions'] ?? []);

        if ($data['is_super_admin'] ?? false) {
            $data['permissions'] = AdminPermissions::allGranted();
        }

        return $data;
    }
}
