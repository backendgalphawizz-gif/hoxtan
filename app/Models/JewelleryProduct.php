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
        'jewellery_sub_sub_category_id',
        'sku',
        'name',
        'description',
        'image',
        'price',
        'making_charge_percent',
        'discount_type',
        'discount_value',
        'weight_grams',
        'metal_type',
        'gender',
        'purity',
        'size',
        'has_size_variants',
        'stock_status',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'making_charge_percent' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'weight_grams' => 'decimal:3',
            'is_active' => 'boolean',
            'has_size_variants' => 'boolean',
            'image' => 'array',
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
            if ($product->has_size_variants) {
                // DB requires price; real amount comes from variants after save.
                if ($product->price === null || $product->price === '') {
                    $product->price = 0;
                }

                return;
            }

            $pricing = JewelleryPricing::calculate(
                $product->metal_type,
                $product->weight_grams,
                $product->making_charge_percent,
                $product->discount_type,
                $product->discount_value,
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

    public function subSubCategory(): BelongsTo
    {
        return $this->belongsTo(JewellerySubSubCategory::class, 'jewellery_sub_sub_category_id');
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

    public function variants(): HasMany
    {
        return $this->hasMany(JewelleryProductVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Keep list/filter columns in sync with the cheapest/first active size variant.
     */
    public function syncVariantDerivedFields(): void
    {
        if (! $this->has_size_variants) {
            return;
        }

        $variant = $this->variants()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if (! $variant) {
            return;
        }

        // Avoid re-entering saved hook loops — update quietly.
        static::withoutEvents(function () use ($variant): void {
            $this->forceFill([
                'size' => $variant->size,
                'weight_grams' => $variant->weight_grams,
                'price' => $variant->price,
            ])->saveQuietly();
        });
    }

    public function resolvedImagePath(): ?string
    {
        return $this->resolvedImagePaths()[0] ?? null;
    }

    /**
     * @return list<string>
     */
    public function resolvedImagePaths(): array
    {
        $image = $this->image;

        if (! is_array($image) || $image === []) {
            $image = $this->decodeImageAttribute($this->getRawOriginal('image'));
        }

        return self::normalizeImagePaths($image);
    }

    /**
     * @return list<string>
     */
    public function imageUrls(): array
    {
        return collect($this->resolvedImagePaths())
            ->map(fn (string $path) => FilamentFormat::storageUrl($path))
            ->filter()
            ->values()
            ->all();
    }

    public function imageUrl(): ?string
    {
        return $this->imageUrls()[0] ?? null;
    }

    /**
     * @return list<array{url: string, is_cover: bool, sort_order: int}>
     */
    public function imageItems(): array
    {
        return collect($this->imageUrls())
            ->values()
            ->map(fn (string $url, int $index): array => [
                'url' => $url,
                'is_cover' => $index === 0,
                'sort_order' => $index,
            ])
            ->all();
    }

    /**
     * @return list<string>
     */
    protected static function normalizeImagePaths(mixed $image): array
    {
        if (blank($image)) {
            return [];
        }

        if (is_string($image)) {
            $decoded = json_decode($image, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return self::normalizeImagePaths($decoded);
            }

            if (str_contains($image, ',')) {
                return collect(explode(',', $image))
                    ->map(fn (string $path) => trim($path))
                    ->filter(fn (string $path) => filled($path))
                    ->values()
                    ->all();
            }

            return filled($image) ? [$image] : [];
        }

        if (! is_array($image)) {
            return [];
        }

        return collect($image)
            ->flatMap(fn (mixed $value): array => self::normalizeImagePaths($value))
            ->filter(fn (mixed $path) => is_string($path) && filled($path))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>|string|null
     */
    protected function decodeImageAttribute(mixed $raw): mixed
    {
        if (blank($raw)) {
            return null;
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : $raw;
        }

        return $raw;
    }

    public function specificationLabel(): string
    {
        $parts = array_filter([
            filled($this->purity) ? $this->purity : null,
            filled($this->size) ? 'Size '.$this->size : null,
            $this->weight_grams !== null ? number_format((float) $this->weight_grams, 1).' gm' : null,
        ]);

        return implode(' | ', $parts);
    }

    /**
     * @return array<string, string>
     */
    public static function sizeOptions(): array
    {
        $options = ['Free Size' => 'Free Size'];

        foreach (range(1, 30) as $size) {
            $options[(string) $size] = (string) $size;
        }

        return $options;
    }
}
