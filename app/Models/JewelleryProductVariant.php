<?php

namespace App\Models;

use App\Support\JewelleryPricing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JewelleryProductVariant extends Model
{
    protected $fillable = [
        'jewellery_product_id',
        'size',
        'weight_grams',
        'price',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'weight_grams' => 'decimal:3',
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (JewelleryProductVariant $variant): void {
            $product = $variant->relationLoaded('product')
                ? $variant->product
                : $variant->product()->first();

            if (! $product) {
                return;
            }

            $pricing = JewelleryPricing::calculate(
                $product->metal_type,
                $variant->weight_grams,
                $product->making_charge_percent,
                $product->discount_type,
                $product->discount_value,
                $product->purity,
            );

            $variant->price = $pricing['total'];
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(JewelleryProduct::class, 'jewellery_product_id');
    }

    public function specificationLabel(?string $purity = null): string
    {
        $parts = array_filter([
            filled($purity) ? $purity : null,
            filled($this->size) ? 'Size '.$this->size : null,
            $this->weight_grams !== null ? number_format((float) $this->weight_grams, 1).' gm' : null,
        ]);

        return implode(' | ', $parts);
    }
}
