<?php

namespace App\Support;

use App\Models\Driver;

class DriverPayload
{
    public static function make(Driver $driver): array
    {
        $profileImageUrl = self::profileImageUrl($driver->profile_image);
        $isOnline = (bool) $driver->is_online;

        return [
            'id' => $driver->id,
            'name' => $driver->name,
            'phone' => $driver->phone,
            'phone_display' => '+91 '.$driver->phone,
            'phone_verified' => true,
            'email' => $driver->email,
            'primary_residence' => $driver->primary_residence,
            'image' => $profileImageUrl,
            'image_url' => $profileImageUrl,
            'profile_image_url' => $profileImageUrl,
            'vehicle_type' => $driver->vehicle_type,
            'vehicle_type_label' => Driver::vehicleTypeOptions()[$driver->vehicle_type] ?? $driver->vehicle_type,
            'vehicle_number' => $driver->vehicle_number,
            'is_active' => (bool) $driver->is_active,
            'is_online' => $isOnline,
            'availability_status' => $isOnline ? 'online' : 'offline',
            'availability_label' => $isOnline ? 'Go Offline' : 'Go Online',
            'last_login_at' => $driver->last_login_at?->toIso8601String(),
        ];
    }

    protected static function profileImageUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        return AssetUrl::publicStorage($path);
    }
}
