<?php

namespace App\Support;

use App\Models\UserAddress;
use Illuminate\Support\Collection;

class AddressPayload
{
    public static function make(UserAddress $address): array
    {
        return [
            'id' => $address->id,
            'address_type' => $address->address_type,
            'address_type_label' => strtoupper($address->address_type),
            'is_default' => $address->is_default,
            'full_name' => $address->full_name,
            'address_line' => $address->address_line,
            'city' => $address->city,
            'state' => $address->state,
            'pincode' => $address->pincode,
            'phone' => $address->phone,
            'phone_display' => '+91 '.$address->phone,
            'full_address' => self::fullAddress($address),
            'created_at' => $address->created_at?->toIso8601String(),
            'updated_at' => $address->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  Collection<int, UserAddress>  $addresses
     */
    public static function collection(Collection $addresses): array
    {
        return $addresses
            ->map(fn (UserAddress $address) => self::make($address))
            ->values()
            ->all();
    }

    protected static function fullAddress(UserAddress $address): string
    {
        return collect([
            $address->address_line,
            $address->city,
            $address->state.' '.$address->pincode,
        ])->filter()->implode(', ');
    }
}
