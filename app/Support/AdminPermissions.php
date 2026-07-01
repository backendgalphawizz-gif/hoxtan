<?php

namespace App\Support;

use App\Models\Admin;

class AdminPermissions
{
    public static function modules(): array
    {
        return config('admin_permissions.modules', []);
    }

    public static function actions(): array
    {
        return config('admin_permissions.actions', []);
    }

    public static function allGranted(): array
    {
        $permissions = [];

        foreach (array_keys(static::modules()) as $module) {
            $permissions[$module] = static::fullModulePermissions();
        }

        return $permissions;
    }

    public static function emptyMatrix(): array
    {
        $permissions = [];

        foreach (array_keys(static::modules()) as $module) {
            $permissions[$module] = static::emptyModulePermissions();
        }

        return $permissions;
    }

    public static function normalize(?array $permissions): array
    {
        $normalized = static::emptyMatrix();

        foreach ($permissions ?? [] as $module => $actions) {
            if (! isset($normalized[$module]) || ! is_array($actions)) {
                continue;
            }

            foreach (array_keys(static::actions()) as $action) {
                $normalized[$module][$action] = (bool) ($actions[$action] ?? false);
            }
        }

        return $normalized;
    }

    public static function admin(): ?Admin
    {
        $admin = auth('admin')->user();

        return $admin instanceof Admin ? $admin : null;
    }

    public static function isSuperAdmin(): bool
    {
        $admin = static::admin();

        return $admin?->isSuperAdmin() ?? false;
    }

    public static function can(string $module, string $action): bool
    {
        $admin = static::admin();

        if (! $admin) {
            return false;
        }

        return $admin->hasPermission($module, $action);
    }

    public static function canViewModule(string $module): bool
    {
        $admin = static::admin();

        if (! $admin) {
            return false;
        }

        return $admin->canViewModule($module);
    }

    public static function canAny(string ...$pairs): bool
    {
        foreach ($pairs as $pair) {
            [$module, $action] = explode('.', $pair, 2);
            if (static::can($module, $action)) {
                return true;
            }
        }

        return false;
    }

    protected static function fullModulePermissions(): array
    {
        $permissions = [];

        foreach (array_keys(static::actions()) as $action) {
            $permissions[$action] = true;
        }

        return $permissions;
    }

    protected static function emptyModulePermissions(): array
    {
        $permissions = [];

        foreach (array_keys(static::actions()) as $action) {
            $permissions[$action] = false;
        }

        return $permissions;
    }
}
