<?php

namespace App\Support;

use App\Models\JewelleryProduct;
use App\Models\JewelleryProductVariant;
use App\Services\GstService;

class JewelleryProductPayload
{
    public static function make(JewelleryProduct $product, ?JewelleryProductVariant $variant = null): array
    {
        $product->loadMissing(['category', 'subCategory', 'subSubCategory', 'variants']);

        if ($variant === null && $product->has_size_variants) {
            $variant = $product->variants->firstWhere('is_active', true) ?? $product->variants->first();
        }

        $weight = $variant?->weight_grams ?? $product->weight_grams;
        $size = $variant?->size ?? $product->size;

        $pricing = JewelleryPricing::calculate(
            $product->metal_type,
            $weight,
            $product->making_charge_percent,
            $product->discount_type,
            $product->discount_value,
            $product->purity,
        );

        // Always price from the current gold/silver rate (not the stored DB price).
        $price = $pricing['total'] > 0
            ? $pricing['total']
            : (float) ($variant?->price ?? $product->price);

        $priceBeforeDiscount = $pricing['subtotal_before_discount'] > 0
            ? $pricing['subtotal_before_discount']
            : $price;

        $gstService = app(GstService::class);
        $gst = $gstService->calculateGstAmount($price);
        $imageUrls = $product->imageUrls();

        $payload = [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'description' => $product->description,
            'image_url' => $imageUrls[0] ?? null,
            'image_urls' => $imageUrls,
            'images' => $product->imageItems(),
            'metal_type' => $product->metal_type,
            'gender' => $product->gender,
            'purity' => $product->purity,
            'size' => $size,
            'weight_grams' => $weight !== null ? (float) $weight : null,
            'specification' => $variant
                ? $variant->specificationLabel($product->purity)
                : $product->specificationLabel(),
            'rate_per_gram' => $pricing['rate_per_gram'],
            'metal_value' => $pricing['metal_value'],
            'making_charge_percent' => $product->making_charge_percent !== null
                ? (float) $product->making_charge_percent
                : null,
            'making_charge_amount' => $pricing['making_charge_amount'] > 0
                ? $pricing['making_charge_amount']
                : null,
            'discount_type' => $product->discount_type,
            'discount_value' => $product->discount_value !== null
                ? (float) $product->discount_value
                : null,
            'discount_amount' => $pricing['discount_amount'] > 0
                ? $pricing['discount_amount']
                : null,
            'price_before_discount' => $priceBeforeDiscount,
            'price' => $price,
            'gst_percent' => $gstService->ratePercent(),
            'gst_amount' => $gst['gst_amount'],
            'total_price' => $gst['total'],
            'stock_status' => 'in_stock',
            'is_available' => true,
            'has_size_variants' => (bool) $product->has_size_variants,
            'variant_id' => $variant?->id,
            'variants' => $product->has_size_variants
                ? $product->variants
                    ->where('is_active', true)
                    ->values()
                    ->map(fn (JewelleryProductVariant $row) => self::variantPayload($product, $row))
                    ->all()
                : [],
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'slug' => $product->category->slug,
            ] : null,
            'sub_category' => $product->subCategory ? [
                'id' => $product->subCategory->id,
                'name' => $product->subCategory->name,
                'slug' => $product->subCategory->slug,
            ] : null,
            'sub_sub_category' => $product->subSubCategory ? [
                'id' => $product->subSubCategory->id,
                'name' => $product->subSubCategory->name,
                'slug' => $product->subSubCategory->slug,
            ] : null,
        ];

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public static function variantPayload(JewelleryProduct $product, JewelleryProductVariant $variant): array
    {
        $pricing = JewelleryPricing::calculate(
            $product->metal_type,
            $variant->weight_grams,
            $product->making_charge_percent,
            $product->discount_type,
            $product->discount_value,
            $product->purity,
        );

        $price = $pricing['total'] > 0 ? $pricing['total'] : (float) $variant->price;
        $gstService = app(GstService::class);
        $gst = $gstService->calculateGstAmount($price);
        $discountType = $pricing['discount_type'];
        $discountValue = $pricing['discount_value'] > 0 ? $pricing['discount_value'] : null;
        $discountPercent = null;

        if ($discountType === 'percent' && $discountValue !== null) {
            $discountPercent = $discountValue;
        } elseif (
            $discountType === 'flat'
            && $pricing['making_charge_amount'] > 0
            && $pricing['discount_amount'] > 0
        ) {
            $discountPercent = round(($pricing['discount_amount'] / $pricing['making_charge_amount']) * 100, 2);
        }

        return [
            'id' => $variant->id,
            'size' => $variant->size,
            'weight_grams' => (float) $variant->weight_grams,
            'specification' => $variant->specificationLabel($product->purity),
            'rate_per_gram' => $pricing['rate_per_gram'],
            'metal_value' => $pricing['metal_value'],
            'making_charge_percent' => $pricing['making_charge_percent'] > 0
                ? $pricing['making_charge_percent']
                : null,
            'making_charge_amount' => $pricing['making_charge_amount'] > 0
                ? $pricing['making_charge_amount']
                : null,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'discount_percent' => $discountPercent,
            'discount_amount' => $pricing['discount_amount'] > 0
                ? $pricing['discount_amount']
                : null,
            'price_before_discount' => $pricing['subtotal_before_discount'] > 0
                ? $pricing['subtotal_before_discount']
                : $price,
            'price' => $price,
            'gst_percent' => $gstService->ratePercent(),
            'gst_amount' => $gst['gst_amount'],
            'total_price' => $gst['total'],
        ];
    }
}
