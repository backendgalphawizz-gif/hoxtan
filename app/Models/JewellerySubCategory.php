<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class JewellerySubCategory extends Model
{
    protected $fillable = [
        'jewellery_category_id',
        'name',
        'slug',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (JewellerySubCategory $subCategory): void {
            if (blank($subCategory->slug)) {
                $subCategory->slug = Str::slug($subCategory->name);
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(JewelleryCategory::class, 'jewellery_category_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(JewelleryProduct::class);
    }
}
