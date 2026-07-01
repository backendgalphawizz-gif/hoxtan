<?php

namespace App\Filament\Concerns;

use App\Support\AdminPermissions;

trait InteractsWithAdminPermissions
{
    abstract protected static function adminPermissionModule(): string;

    public static function canAccess(): bool
    {
        return AdminPermissions::canViewModule(static::adminPermissionModule());
    }

    public static function canCreate(): bool
    {
        return AdminPermissions::can(static::adminPermissionModule(), 'create');
    }

    public static function canEdit($record): bool
    {
        return AdminPermissions::can(static::adminPermissionModule(), 'edit');
    }

    public static function canDelete($record): bool
    {
        return AdminPermissions::can(static::adminPermissionModule(), 'delete');
    }

    public static function canView($record): bool
    {
        return AdminPermissions::can(static::adminPermissionModule(), 'view');
    }
}
