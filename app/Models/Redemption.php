<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Redemption extends Model
{
    protected $fillable = [
        'reference_id',
        'user_id',
        'metal_type',
        'quantity_grams',
        'amount',
        'status',
        'delivery_address',
        'tracking_number',
        'courier_name',
        'dispatched_at',
        'delivered_at',
        'admin_notes',
        'rejection_reason',
        'processed_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity_grams' => 'decimal:4',
            'amount' => 'decimal:2',
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'processed_by');
    }

    protected static function booted(): void
    {
        static::creating(function (Redemption $redemption) {
            if (empty($redemption->reference_id)) {
                $redemption->reference_id = 'RED-'.strtoupper(uniqid());
            }
        });
    }
}
