<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Employee extends Authenticatable implements FilamentUser, HasAvatar, HasName
{
    use HasFactory, Notifiable;

    public const ROLE_STAFF = 'staff';

    public const ROLE_EMPLOYEE = 'employee';

    protected $fillable = [
        'department_id',
        'role',
        'name',
        'email',
        'phone',
        'employee_code',
        'password',
        'is_active',
        'created_by_admin_id',
        'created_by_employee_id',
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

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function createdByEmployee(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by_employee_id');
    }

    public function createdEmployees(): HasMany
    {
        return $this->hasMany(self::class, 'created_by_employee_id');
    }

    public function createdUsers(): HasMany
    {
        return $this->hasMany(User::class, 'created_by_employee_id');
    }

    /**
     * @param  Builder<Employee>  $query
     * @return Builder<Employee>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<Employee>  $query
     * @return Builder<Employee>
     */
    public function scopeStaff(Builder $query): Builder
    {
        return $query->where('role', self::ROLE_STAFF);
    }

    /**
     * @param  Builder<Employee>  $query
     * @return Builder<Employee>
     */
    public function scopeTeamEmployees(Builder $query): Builder
    {
        return $query->where('role', self::ROLE_EMPLOYEE);
    }

    public function isStaff(): bool
    {
        return $this->role === self::ROLE_STAFF;
    }

    public function isTeamEmployee(): bool
    {
        return $this->role === self::ROLE_EMPLOYEE;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'employee' && $this->is_active;
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
