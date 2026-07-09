<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Support\FilamentFormat;
use App\Support\JewelleryPricing;

class JewelleryProduct extends Model
{
    protected $fillable = [
        'jewellery_category_id',
        'jewellery_sub_category_id',
        'sku',
        'name',
        'description',
        'image',
        'price',
        'making_charge_percent',
        'weight_grams',
        'metal_type',
        'purity',
        'stock_status',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'making_charge_percent' => 'decimal:2',
            'weight_grams' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (JewelleryProduct $product): void {
            if (blank($product->sku)) {
                $product->sku = 'JWL-'.strtoupper(uniqid());
            }
        });

        static::saving(function (JewelleryProduct $product): void {
            $pricing = JewelleryPricing::calculate(
                $product->metal_type,
                $product->weight_grams,
                $product->making_charge_percent,
            );

            $product->price = $pricing['total'];
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(JewelleryCategory::class, 'jewellery_category_id');
    }

    public function subCategory(): BelongsTo
    {
        return $this->belongsTo(JewellerySubCategory::class, 'jewellery_sub_category_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(JewelleryOrderItem::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(JewelleryCartItem::class);
    }

    public function views(): HasMany
    {
        return $this->hasMany(JewelleryProductView::class);
    }

    public function resolvedImagePath(): ?string
    {
        $image = $this->image;

        if (is_array($image)) {
            return $image[0] ?? null;
        }

        return is_string($image) ? $image : null;
    }

    public function imageUrl(): ?string
    {
        return FilamentFormat::storageUrl($this->resolvedImagePath());
    }

    public function specificationLabel(): string
    {
        $parts = array_filter([
            filled($this->purity) ? $this->purity : null,
            $this->weight_grams !== null ? number_format((float) $this->weight_grams, 1).' gm' : null,
        ]);

        return implode(' | ', $parts);
    }
}
