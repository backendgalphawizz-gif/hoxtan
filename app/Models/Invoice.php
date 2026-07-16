<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $fillable = [
        'invoice_number',
        'user_id',
        'investment_id',
        'jewellery_order_id',
        'old_gold_booking_id',
        'subtotal',
        'gst_amount',
        'total_amount',
        'metal_type',
        'quantity_grams',
        'rate_per_gram',
        'file_path',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'gst_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'quantity_grams' => 'decimal:4',
            'rate_per_gram' => 'decimal:2',
            'issued_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }

    public function jewelleryOrder(): BelongsTo
    {
        return $this->belongsTo(JewelleryOrder::class);
    }

    public function oldGoldBooking(): BelongsTo
    {
        return $this->belongsTo(OldGoldBooking::class);
    }

    public function isJewellery(): bool
    {
        return $this->jewellery_order_id !== null;
    }

    public function isSellJewellery(): bool
    {
        return $this->old_gold_booking_id !== null;
    }

    public function sourceType(): string
    {
        if ($this->isSellJewellery()) {
            return 'sell_jewellery';
        }

        return $this->isJewellery() ? 'jewellery' : 'investment';
    }

    public function sourceReference(): ?string
    {
        if ($this->isSellJewellery()) {
            return $this->oldGoldBooking?->booking_number;
        }

        if ($this->isJewellery()) {
            return $this->jewelleryOrder?->order_number;
        }

        return $this->investment?->reference_id;
    }
}
