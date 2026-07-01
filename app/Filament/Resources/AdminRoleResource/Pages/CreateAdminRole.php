<?php

namespace App\Filament\Resources\AdminRoleResource\Pages;

use App\Filament\Resources\AdminRoleResource;
use App\Support\AdminPermissions;
use App\Filament\Resources\Pages\BaseCreateRecord;

class CreateAdminRole extends BaseCreateRecord
{
    protected static string $resource = AdminRoleResource::class;

    public function mount(): void
    {
        parent::mount();

        $this->data['permissions'] = AdminPermissions::emptyMatrix();
    }

    public function selectAllPermissions(): void
    {
        $this->data['permissions'] = AdminPermissions::allGranted();
    }

    public function clearAllPermissions(): void
    {
        $this->data['permissions'] = AdminPermissions::emptyMatrix();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['permissions'] = AdminPermissions::normalize($data['permissions'] ?? []);

        if ($data['is_super_admin'] ?? false) {
            $data['permissions'] = AdminPermissions::allGranted();
        }

        return $data;
    }
}
