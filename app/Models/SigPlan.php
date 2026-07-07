<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SigPlan extends Model
{
    protected $fillable = [
        'plan_number',
        'user_id',
        'metal_type',
        'frequency',
        'amount',
        'status',
        'linked_bank_name',
        'linked_bank_last4',
        'total_installments',
        'completed_installments',
        'total_invested',
        'metal_accumulated_grams',
        'next_debit_at',
        'activated_at',
        'paused_at',
        'stopped_at',
        'admin_notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'total_invested' => 'decimal:2',
            'metal_accumulated_grams' => 'decimal:4',
            'next_debit_at' => 'datetime',
            'activated_at' => 'datetime',
            'paused_at' => 'datetime',
            'stopped_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SigPlan $plan): void {
            if (blank($plan->plan_number)) {
                $plan->plan_number = 'SIG-'.strtoupper(uniqid());
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(SigInstallment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function getTitleLabelAttribute(): string
    {
        return ucfirst($this->frequency).' '.ucfirst($this->metal_type).' SIG';
    }

    public function getProgressLabelAttribute(): string
    {
        if ($this->total_installments) {
            return $this->completed_installments.'/'.$this->total_installments;
        }

        return (string) $this->completed_installments;
    }

    public function getLinkedBankLabelAttribute(): ?string
    {
        if (blank($this->linked_bank_name)) {
            return null;
        }

        $last4 = $this->linked_bank_last4 ? ' ••••'.$this->linked_bank_last4 : '';

        return $this->linked_bank_name.$last4;
    }
}
