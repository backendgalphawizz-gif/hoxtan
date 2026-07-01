<?php

namespace App\Models;

use App\Support\AdminPermissions;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable implements FilamentUser, HasAvatar, HasName
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'admin_role_id',
        'name',
        'email',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(AdminRole::class, 'admin_role_id');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && ($this->isSuperAdmin() || filled($this->admin_role_id));
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->role?->is_super_admin;
    }

    public function hasPermission(string $module, string $action): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $permissions = AdminPermissions::normalize($this->role?->permissions);

        return (bool) ($permissions[$module][$action] ?? false);
    }

    public function canViewModule(string $module): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $permissions = AdminPermissions::normalize($this->role?->permissions)[$module] ?? [];

        return ($permissions['view'] ?? false)
            || ($permissions['create'] ?? false)
            || ($permissions['edit'] ?? false)
            || ($permissions['delete'] ?? false)
            || ($permissions['export'] ?? false);
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        $initial = strtoupper(substr($this->name, 0, 1));

        return 'https://ui-avatars.com/api/?name='.urlencode($initial).'&color=d4a017&background=1e293b&size=128&bold=true';
    }
}
