<?php

namespace App\Models;

use App\Support\AdminPermissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AdminRole extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'permissions',
        'is_active',
        'is_super_admin',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'is_active' => 'boolean',
            'is_super_admin' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (AdminRole $role): void {
            if (blank($role->slug)) {
                $role->slug = Str::slug($role->name);
            }

            if ($role->is_super_admin) {
                $role->permissions = AdminPermissions::allGranted();
            }
        });
    }

    public function admins(): HasMany
    {
        return $this->hasMany(Admin::class);
    }
}
