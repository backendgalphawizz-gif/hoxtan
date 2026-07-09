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
        'payment_id',
        'subtotal',
        'metal_value',
        'making_charge_amount',
        'gst_percent',
        'gst_amount',
        'discount_amount',
        'total_amount',
        'status',
        'shipping_address',
        'shipping_name',
        'shipping_phone',
        'shipping_address_type',
        'expected_delivery_date',
        'tracking_number',
        'courier_name',
        'dispatched_at',
        'delivered_at',
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
            'expected_delivery_date' => 'date',
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(UserAddress::class, 'user_address_id');
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
