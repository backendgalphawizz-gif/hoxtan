<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JewelleryCategory;
use App\Models\JewelleryProduct;
use App\Models\JewellerySubCategory;
use App\Support\ApiResponse;
use App\Support\JewelleryPricing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JewelleryController extends Controller
{
    public function categories(Request $request): JsonResponse
    {
        $metalType = $request->query('metal_type');

        if ($metalType !== null) {
            $request->validate([
                'metal_type' => ['required', Rule::in(['gold', 'silver'])],
            ]);
        }

        $categories = JewelleryCategory::query()
            ->where('is_active', true)
            ->when(
                filled($metalType),
                fn (Builder $query) => $query->where(function (Builder $inner) use ($metalType): void {
                    $inner->where('metal_type', $metalType)->orWhere('metal_type', 'both');
                })
            )
            ->orderBy('sort_order')
            ->get()
            ->map(fn (JewelleryCategory $category) => $this->categoryPayload($category));

        return ApiResponse::success(['categories' => $categories]);
    }

    public function subCategories(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:jewellery_categories,id'],
        ]);

        $subCategories = JewellerySubCategory::query()
            ->where('jewellery_category_id', $data['category_id'])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (JewellerySubCategory $sub) => [
                'id' => $sub->id,
                'name' => $sub->name,
                'slug' => $sub->slug,
            ]);

        return ApiResponse::success(['sub_categories' => $subCategories]);
    }

    public function products(Request $request): JsonResponse
    {
        $data = $request->validate([
            'metal_type' => ['nullable', Rule::in(['gold', 'silver'])],
            'category_id' => ['nullable', 'integer', 'exists:jewellery_categories,id'],
            'sub_category_id' => ['nullable', 'integer', 'exists:jewellery_sub_categories,id'],
        ]);

        $products = JewelleryProduct::query()
            ->with(['category', 'subCategory'])
            ->where('is_active', true)
            ->when(filled($data['metal_type'] ?? null), fn (Builder $q) => $q->where('metal_type', $data['metal_type']))
            ->when(filled($data['category_id'] ?? null), fn (Builder $q) => $q->where('jewellery_category_id', $data['category_id']))
            ->when(filled($data['sub_category_id'] ?? null), fn (Builder $q) => $q->where('jewellery_sub_category_id', $data['sub_category_id']))
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get()
            ->map(fn (JewelleryProduct $product) => $this->productPayload($product));

        return ApiResponse::success(['products' => $products]);
    }

    public function show(JewelleryProduct $product): JsonResponse
    {
        if (! $product->is_active) {
            return ApiResponse::error('Product not found.', [], 404);
        }

        $product->load(['category', 'subCategory']);

        return ApiResponse::success(['product' => $this->productPayload($product)]);
    }

    private function categoryPayload(JewelleryCategory $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'metal_type' => $category->metal_type,
            'sort_order' => $category->sort_order,
        ];
    }

    private function productPayload(JewelleryProduct $product): array
    {
        $pricing = JewelleryPricing::calculate(
            $product->metal_type,
            $product->weight_grams,
            $product->making_charge_percent,
        );

        return [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'description' => $product->description,
            'image_url' => $product->imageUrl(),
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
            'price' => (float) $product->price,
            'stock_status' => $product->stock_status,
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
