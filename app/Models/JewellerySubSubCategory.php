<?php

namespace App\Models;

use App\Models\Concerns\SyncsSortOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class JewellerySubSubCategory extends Model
{
    use SyncsSortOrder;

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
            'sort_order' => 'integer',
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

    protected static function applySortOrderScope(Builder $query, Model $model): void
    {
        $parentId = $model->getAttribute('jewellery_sub_category_id');

        if ($parentId !== null) {
            $query->where('jewellery_sub_category_id', $parentId);
        }
    }

    public static function ensureSortSequence(): void
    {
        static::query()
            ->select('jewellery_sub_category_id')
            ->distinct()
            ->pluck('jewellery_sub_category_id')
            ->each(function ($parentId): void {
                static::resequenceGroup(
                    static::query()->where('jewellery_sub_category_id', $parentId)
                );
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
