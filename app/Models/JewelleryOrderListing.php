<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JewelleryOrderListing extends Model
{
    protected $table = 'jewellery_order_listings';

    protected $primaryKey = 'listing_key';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'source_id' => 'integer',
            'amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'listing_key';
    }

    public function isBuy(): bool
    {
        return $this->listing_type === 'buy';
    }

    public function isSell(): bool
    {
        return $this->listing_type === 'sell';
    }

    public function buyOrder(): ?JewelleryOrder
    {
        if (! $this->isBuy()) {
            return null;
        }

        return JewelleryOrder::query()
            ->with(['user', 'payment', 'items.product', 'address', 'driver', 'emiPlan'])
            ->find($this->source_id);
    }

    public function sellBooking(): ?OldGoldBooking
    {
        if (! $this->isSell()) {
            return null;
        }

        return OldGoldBooking::query()
            ->with(['user', 'payment', 'driver'])
            ->find($this->source_id);
    }

    public function productSummary(): string
    {
        if ($this->isSell()) {
            return (string) ($this->product_summary ?: '—');
        }

        $order = $this->buyOrder();

        if ($order === null) {
            return '—';
        }

        return $order->items
            ->map(fn ($item) => ($item->product?->name ?? 'Product').' × '.$item->quantity)
            ->implode(', ') ?: '—';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
