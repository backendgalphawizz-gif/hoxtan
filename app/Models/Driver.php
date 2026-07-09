<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'email',
        'vehicle_type',
        'vehicle_number',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
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
