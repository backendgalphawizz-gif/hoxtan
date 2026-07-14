<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SigInstallment extends Model
{
    protected $fillable = [
        'reference_id',
        'sig_plan_id',
        'user_id',
        'investment_id',
        'amount',
        'quantity_grams',
        'rate_per_gram',
        'status',
        'scheduled_at',
        'processed_at',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'quantity_grams' => 'decimal:6',
            'rate_per_gram' => 'decimal:2',
            'scheduled_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SigInstallment $installment): void {
            if (blank($installment->reference_id)) {
                $installment->reference_id = 'SIGT-'.strtoupper(uniqid());
            }
        });
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SigPlan::class, 'sig_plan_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }
}
