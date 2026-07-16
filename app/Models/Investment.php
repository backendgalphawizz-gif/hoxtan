<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Investment extends Model
{
    protected $fillable = [
        'reference_id',
        'user_id',
        'sig_plan_id',
        'metal_type',
        'type',
        'quantity_grams',
        'remaining_grams',
        'rate_per_gram',
        'amount',
        'gst_amount',
        'total_amount',
        'status',
        'hold_started_at',
        'hold_bonus_credited_at',
        'purpose',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_grams' => 'decimal:4',
            'remaining_grams' => 'decimal:4',
            'rate_per_gram' => 'decimal:2',
            'amount' => 'decimal:2',
            'gst_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'hold_started_at' => 'datetime',
            'hold_bonus_credited_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sigPlan(): BelongsTo
    {
        return $this->belongsTo(SigPlan::class);
    }

    public function holdingCertificate(): HasOne
    {
        return $this->hasOne(HoldingCertificate::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Investment $investment) {
            if (empty($investment->reference_id)) {
                $investment->reference_id = 'INV-'.strtoupper(uniqid());
            }
        });
    }
}
