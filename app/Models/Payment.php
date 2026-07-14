<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    protected $fillable = [
        'reference_id', 'user_id', 'payable_type', 'payable_id', 'amount', 'currency',
        'status', 'gateway', 'gateway_reference', 'gateway_payment_id', 'failure_reason', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function jewelleryOrders(): HasMany
    {
        return $this->hasMany(JewelleryOrder::class);
    }
}
