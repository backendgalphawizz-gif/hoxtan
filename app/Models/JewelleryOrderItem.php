<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JewelleryOrderItem extends Model
{
    protected $fillable = [
        'jewellery_order_id',
        'jewellery_product_id',
        'jewellery_product_variant_id',
        'size',
        'weight_grams',
        'quantity',
        'unit_price',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'weight_grams' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(JewelleryOrder::class, 'jewellery_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(JewelleryProduct::class, 'jewellery_product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(JewelleryProductVariant::class, 'jewellery_product_variant_id');
    }
}
