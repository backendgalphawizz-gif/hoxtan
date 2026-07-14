<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JewelleryEmiRefundRequest extends Model
{
    protected $fillable = [
        'reference_id',
        'jewellery_order_id',
        'user_id',
        'paid_amount',
        'cancellation_fee_percent',
        'cancellation_fee_amount',
        'gst_percent',
        'gst_amount',
        'deduction_amount',
        'refund_amount',
        'bank_name',
        'account_holder_name',
        'account_number',
        'ifsc_code',
        'status',
        'requested_at',
        'auto_approve_at',
        'auto_approved',
        'reviewed_at',
        'reviewed_by',
        'rejection_reason',
        'cancellation_reason',
        'refund_reference',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'paid_amount' => 'decimal:2',
            'cancellation_fee_percent' => 'decimal:2',
            'cancellation_fee_amount' => 'decimal:2',
            'gst_percent' => 'decimal:2',
            'gst_amount' => 'decimal:2',
            'deduction_amount' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'requested_at' => 'datetime',
            'auto_approve_at' => 'datetime',
            'auto_approved' => 'boolean',
            'reviewed_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (JewelleryEmiRefundRequest $request): void {
            if (blank($request->reference_id)) {
                $request->reference_id = 'EMIR-'.strtoupper(uniqid());
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(JewelleryOrder::class, 'jewellery_order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return in_array($this->status, ['approved', 'auto_approved', 'refunded'], true);
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
