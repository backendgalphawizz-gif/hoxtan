<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OldGoldBooking extends Model
{
    protected $fillable = [
        'booking_number', 'user_id', 'payment_id', 'estimated_weight_grams',
        'quoted_amount', 'final_amount', 'status', 'pickup_address', 'admin_notes', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'estimated_weight_grams' => 'decimal:3',
            'quoted_amount' => 'decimal:2',
            'final_amount' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
