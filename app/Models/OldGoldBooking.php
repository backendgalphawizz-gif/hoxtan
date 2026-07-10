<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OldGoldBooking extends Model
{
    protected $fillable = [
        'booking_number',
        'user_id',
        'payment_id',
        'metal_type',
        'purity',
        'item_name',
        'estimated_weight_grams',
        'rate_per_gram',
        'quoted_amount',
        'final_amount',
        'identity_owner',
        'sell_location',
        'user_address_id',
        'driver_id',
        'driver_assigned_at',
        'status',
        'pickup_address',
        'pickup_name',
        'pickup_phone',
        'documents',
        'admin_notes',
        'accepted_at',
        'pickup_scheduled_at',
        'picked_up_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'estimated_weight_grams' => 'decimal:3',
            'rate_per_gram' => 'decimal:2',
            'quoted_amount' => 'decimal:2',
            'final_amount' => 'decimal:2',
            'documents' => 'array',
            'driver_assigned_at' => 'datetime',
            'accepted_at' => 'datetime',
            'pickup_scheduled_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (OldGoldBooking $booking): void {
            if (! $booking->isDirty('driver_id')) {
                return;
            }

            $booking->driver_assigned_at = $booking->driver_id ? now() : null;
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function userAddress(): BelongsTo
    {
        return $this->belongsTo(UserAddress::class);
    }
}
