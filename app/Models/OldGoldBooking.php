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
        'delivery_otp',
        'documents',
        'admin_notes',
        'accepted_at',
        'pickup_scheduled_at',
        'driver_accepted_at',
        'customer_verified_at',
        'pickup_proof_images',
        'pickup_failure_reason',
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
            'pickup_proof_images' => 'array',
            'driver_assigned_at' => 'datetime',
            'driver_accepted_at' => 'datetime',
            'customer_verified_at' => 'datetime',
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

            if ($booking->driver_id) {
                $booking->driver_assigned_at = now();

                if (in_array($booking->status, ['pending', 'accepted', 'pickup_scheduling'], true)) {
                    $booking->status = 'processing';
                }

                return;
            }

            $booking->driver_assigned_at = null;
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

    /**
     * Driver and customer APIs accept numeric id or booking_number (e.g. SELL96309).
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        if ($field !== null) {
            return parent::resolveRouteBindingQuery($query, $value, $field);
        }

        $identifier = ltrim((string) $value, '#');

        if (ctype_digit($identifier)) {
            return $query->whereKey($identifier);
        }

        return $query->where('booking_number', $identifier);
    }
}
