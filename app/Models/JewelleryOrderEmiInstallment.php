<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JewelleryOrderEmiInstallment extends Model
{
    protected $fillable = [
        'jewellery_order_id',
        'installment_number',
        'amount',
        'due_date',
        'status',
        'paid_at',
        'marked_paid_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'installment_number' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(JewelleryOrder::class, 'jewellery_order_id');
    }

    public function markedPaidByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'marked_paid_by');
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function label(): string
    {
        return 'EMI '.$this->installment_number;
    }
}
