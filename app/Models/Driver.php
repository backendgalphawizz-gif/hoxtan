<?php

namespace App\Models;

use App\Support\PhoneRules;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Driver extends Model
{
    use HasApiTokens;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'profile_image',
        'primary_residence',
        'vehicle_type',
        'vehicle_number',
        'notes',
        'is_active',
        'is_online',
        'last_login_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_online' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Driver $driver): void {
            if (filled($driver->phone)) {
                $driver->phone = PhoneRules::normalize($driver->phone);
            }
        });
    }

    public static function vehicleTypeOptions(): array
    {
        return [
            'bike' => 'Bike',
            'car' => 'Car',
            'van' => 'Van',
            'other' => 'Other',
        ];
    }
}
