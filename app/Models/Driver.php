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
        'vehicle_type',
        'vehicle_number',
        'notes',
        'is_active',
        'last_login_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
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
