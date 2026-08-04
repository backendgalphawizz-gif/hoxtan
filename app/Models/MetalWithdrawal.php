<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetalWithdrawal extends Model
{
    protected $fillable = [
        'reference_id',
        'user_id',
        'asset_source',
        'metal_type',
        'input_mode',
        'quantity_grams',
        'rate_per_gram',
        'amount',
        'status',
        'bank_name',
        'account_holder_name',
        'account_number',
        'ifsc_code',
        'sig_plan_id',
        'investment_id',
        'source_lot_id',
        'from_holdings',
        'admin_notes',
        'rejection_reason',
        'payout_reference',
        'auto_approved',
        'requested_at',
        'auto_approve_at',
        'reviewed_by',
        'reviewed_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity_grams' => 'decimal:4',
            'rate_per_gram' => 'decimal:2',
            'amount' => 'decimal:2',
            'auto_approved' => 'boolean',
            'from_holdings' => 'boolean',
            'requested_at' => 'datetime',
            'auto_approve_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (MetalWithdrawal $withdrawal): void {
            if (blank($withdrawal->reference_id)) {
                $withdrawal->reference_id = 'WDR-'.strtoupper(uniqid());
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sigPlan(): BelongsTo
    {
        return $this->belongsTo(SigPlan::class);
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function maskedAccountNumber(): ?string
    {
        if (blank($this->account_number)) {
            return null;
        }

        $number = (string) $this->account_number;

        if (strlen($number) <= 4) {
            return $number;
        }

        return str_repeat('X', max(0, strlen($number) - 4)).substr($number, -4);
    }
}
