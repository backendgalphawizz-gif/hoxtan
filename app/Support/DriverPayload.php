<?php

namespace App\Support;

use App\Models\Driver;

class DriverPayload
{
    public static function make(Driver $driver): array
    {
        return [
            'id' => $driver->id,
            'name' => $driver->name,
            'phone' => $driver->phone,
            'phone_display' => '+91 '.$driver->phone,
            'email' => $driver->email,
            'vehicle_type' => $driver->vehicle_type,
            'vehicle_type_label' => Driver::vehicleTypeOptions()[$driver->vehicle_type] ?? $driver->vehicle_type,
            'vehicle_number' => $driver->vehicle_number,
            'is_active' => (bool) $driver->is_active,
            'last_login_at' => $driver->last_login_at?->toIso8601String(),
        ];
    }
}
