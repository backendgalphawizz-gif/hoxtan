<?php

namespace App\Support;

use App\Models\JewelleryProduct;
use App\Services\GstService;

class JewelleryProductPayload
{
    public static function make(JewelleryProduct $product): array
    {
        $pricing = JewelleryPricing::calculate(
            $product->metal_type,
            $product->weight_grams,
            $product->making_charge_percent,
        );

        // Always price from the current gold/silver rate (not the stored DB price).
        $price = $pricing['total'] > 0
            ? $pricing['total']
            : (float) $product->price;

        $gstService = app(GstService::class);
        $gst = $gstService->calculateGstAmount($price);

        return [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'description' => $product->description,
            'image_url' => $product->imageUrl(),
            'image_urls' => $product->imageUrls(),
            'metal_type' => $product->metal_type,
            'purity' => $product->purity,
            'weight_grams' => $product->weight_grams !== null ? (float) $product->weight_grams : null,
            'specification' => $product->specificationLabel(),
            'rate_per_gram' => $pricing['rate_per_gram'],
            'metal_value' => $pricing['metal_value'],
            'making_charge_percent' => $product->making_charge_percent !== null
                ? (float) $product->making_charge_percent
                : null,
            'making_charge_amount' => $pricing['making_charge_amount'] > 0
                ? $pricing['making_charge_amount']
                : null,
            'price' => $price,
            'gst_percent' => $gstService->ratePercent(),
            'gst_amount' => $gst['gst_amount'],
            'total_price' => $gst['total'],
            'stock_status' => 'in_stock',
            'is_available' => true,
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
        ];
    }
}
