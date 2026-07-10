<?php

namespace App\Models;

use App\Support\PhoneRules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        static::creating(function (Driver $driver): void {
            if ($driver->is_active === null) {
                $driver->is_active = true;
            }

            if ($driver->is_online === null) {
                $driver->is_online = true;
            }
        });

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

    /**
     * @return Builder<Driver>
     */
    public static function assignableQuery(?int $includeDriverId = null): Builder
    {
        return static::query()
            ->where(function (Builder $query) use ($includeDriverId): void {
                $query->where('is_active', true);

                if ($includeDriverId !== null) {
                    $query->orWhere('id', $includeDriverId);
                }
            })
            ->orderBy('name');
    }

    public static function assignmentOptionLabel(self $driver): string
    {
        $availability = $driver->is_online ? 'Online' : 'Offline';
        $statusSuffix = $driver->is_active ? '' : ' · Inactive';

        return "{$driver->name} — +91 {$driver->phone} · {$availability}{$statusSuffix}";
    }

    /**
     * @return array<int, string>
     */
    public static function assignmentOptions(?int $includeDriverId = null): array
    {
        return static::assignableQuery($includeDriverId)
            ->get()
            ->mapWithKeys(fn (self $driver): array => [
                $driver->id => static::assignmentOptionLabel($driver),
            ])
            ->all();
    }

    public function jewelleryOrders(): HasMany
    {
        return $this->hasMany(JewelleryOrder::class);
    }

    public function oldGoldBookings(): HasMany
    {
        return $this->hasMany(OldGoldBooking::class);
    }
}
