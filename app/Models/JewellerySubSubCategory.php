<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class JewellerySubSubCategory extends Model
{
    protected $fillable = [
        'jewellery_sub_category_id',
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
        static::saving(function (JewellerySubSubCategory $subSubCategory): void {
            if (blank($subSubCategory->slug)) {
                $subSubCategory->slug = Str::slug($subSubCategory->name);
            }
        });
    }

    public function subCategory(): BelongsTo
    {
        return $this->belongsTo(JewellerySubCategory::class, 'jewellery_sub_category_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(JewelleryProduct::class);
    }
}
