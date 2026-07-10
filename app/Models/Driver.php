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
        'registration_card_image',
        'licence_no',
        'licence_image',
        'emergency_no',
        'aadhaar_front_image',
        'aadhaar_back_image',
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
            foreach (['phone', 'emergency_no'] as $field) {
                if (filled($driver->{$field})) {
                    $driver->{$field} = PhoneRules::normalize($driver->{$field});
                }
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
