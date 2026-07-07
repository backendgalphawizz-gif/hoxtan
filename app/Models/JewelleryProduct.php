<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JewelleryProduct extends Model
{
    protected $fillable = [
        'sku', 'name', 'description', 'price', 'weight_grams',
        'metal_type', 'stock_status', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'weight_grams' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(JewelleryOrderItem::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(JewelleryCartItem::class);
    }
}
