<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JewelleryOrder extends Model
{
    protected $fillable = [
        'order_number',
        'user_id',
        'user_address_id',
        'driver_id',
        'driver_assigned_at',
        'picked_up_at',
        'payment_id',
        'subtotal',
        'metal_value',
        'making_charge_amount',
        'gst_percent',
        'gst_amount',
        'discount_amount',
        'total_amount',
        'payment_mode',
        'jewellery_emi_plan_id',
        'emi_tenure',
        'total_emi_cost',
        'monthly_emi_amount',
        'status',
        'shipping_address',
        'shipping_name',
        'shipping_phone',
        'shipping_address_type',
        'expected_delivery_date',
        'delivery_otp',
        'tracking_number',
        'courier_name',
        'dispatched_at',
        'delivered_at',
        'delivery_failure_reason',
        'delivery_proof_image',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'metal_value' => 'decimal:2',
            'making_charge_amount' => 'decimal:2',
            'gst_percent' => 'decimal:2',
            'gst_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'total_emi_cost' => 'decimal:2',
            'monthly_emi_amount' => 'decimal:2',
            'emi_tenure' => 'integer',
            'expected_delivery_date' => 'date',
            'driver_assigned_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (JewelleryOrder $order): void {
            if (! $order->isDirty('driver_id')) {
                return;
            }

            if ($order->driver_id) {
                $order->driver_assigned_at = now();

                if ($order->status === 'pending') {
                    $order->status = 'processing';
                }

                return;
            }

            $order->driver_assigned_at = null;
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(UserAddress::class, 'user_address_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function emiPlan(): BelongsTo
    {
        return $this->belongsTo(JewelleryEmiPlan::class, 'jewellery_emi_plan_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(JewelleryOrderItem::class);
    }
}
