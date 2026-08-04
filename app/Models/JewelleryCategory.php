<?php

namespace App\Models;

use App\Models\Concerns\SyncsSortOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class JewelleryCategory extends Model
{
    use SyncsSortOrder;

    protected $fillable = [
        'name',
        'slug',
        'metal_type',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (JewelleryCategory $category): void {
            if (blank($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    public static function ensureSortSequence(): void
    {
        static::resequenceGroup(static::query());
    }

    public function subCategories(): HasMany
    {
        return $this->hasMany(JewellerySubCategory::class)->orderBy('sort_order')->orderBy('id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(JewelleryProduct::class);
    }
}
