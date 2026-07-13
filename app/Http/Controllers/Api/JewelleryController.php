<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JewelleryCategory;
use App\Models\JewelleryProduct;
use App\Models\JewelleryProductView;
use App\Models\JewellerySubCategory;
use App\Models\JewellerySubSubCategory;
use App\Models\User;
use App\Services\JewelleryEmiService;
use App\Support\ApiResponse;
use App\Support\JewelleryProductPayload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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

    public function subSubCategories(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sub_category_id' => ['required', 'integer', 'exists:jewellery_sub_categories,id'],
        ]);

        $subSubCategories = JewellerySubSubCategory::query()
            ->where('jewellery_sub_category_id', $data['sub_category_id'])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (JewellerySubSubCategory $subSub) => [
                'id' => $subSub->id,
                'name' => $subSub->name,
                'slug' => $subSub->slug,
            ]);

        return ApiResponse::success(['sub_sub_categories' => $subSubCategories]);
    }

    public function emiPlans(Request $request, JewelleryEmiService $emi): JsonResponse
    {
        $data = $request->validate([
            'order_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $orderAmount = isset($data['order_amount']) ? (float) $data['order_amount'] : null;
        $plans = $emi->listPlans($orderAmount);

        return ApiResponse::success([
            'emi_plans' => $plans,
            'order_amount' => $orderAmount,
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        $data = $request->validate([
            'metal_type' => ['nullable', Rule::in(['gold', 'silver'])],
            'category_id' => ['nullable', 'integer', 'exists:jewellery_categories,id'],
            'sub_category_id' => ['nullable', 'integer', 'exists:jewellery_sub_categories,id'],
            'sub_sub_category_id' => ['nullable', 'integer', 'exists:jewellery_sub_sub_categories,id'],
        ]);

        $products = JewelleryProduct::query()
            ->with(['category', 'subCategory', 'subSubCategory'])
            ->where('is_active', true)
            ->when(filled($data['metal_type'] ?? null), fn (Builder $q) => $q->where('metal_type', $data['metal_type']))
            ->when(filled($data['category_id'] ?? null), fn (Builder $q) => $q->where('jewellery_category_id', $data['category_id']))
            ->when(filled($data['sub_category_id'] ?? null), fn (Builder $q) => $q->where('jewellery_sub_category_id', $data['sub_category_id']))
            ->when(filled($data['sub_sub_category_id'] ?? null), fn (Builder $q) => $q->where('jewellery_sub_sub_category_id', $data['sub_sub_category_id']))
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get()
            ->map(fn (JewelleryProduct $product) => JewelleryProductPayload::make($product));

        return ApiResponse::success(['products' => $products]);
    }

    public function show(Request $request, JewelleryProduct $product): JsonResponse
    {
        if (! $product->is_active) {
            return ApiResponse::error('Product not found.', [], 404);
        }

        $request->validate([
            'recently_viewed_ids' => ['nullable', 'array', 'max:20'],
            'recently_viewed_ids.*' => ['integer', 'distinct'],
        ]);

        $product->load(['category', 'subCategory', 'subSubCategory']);

        /** @var User|null $user */
        $user = $request->user('sanctum');

        if ($user) {
            $this->recordProductView($user, $product);
        }

        $excludeIds = [$product->id];

        $recentlyViewed = $this->recentlyViewedProducts(
            $user,
            $excludeIds,
            $request->input('recently_viewed_ids', []),
        );

        $excludeIds = array_values(array_unique(array_merge(
            $excludeIds,
            $recentlyViewed->pluck('id')->all(),
        )));

        $similarProducts = $this->similarProducts($product, $excludeIds);

        return ApiResponse::success([
            'product' => JewelleryProductPayload::make($product),
            'recently_viewed' => $recentlyViewed
                ->map(fn (JewelleryProduct $item) => JewelleryProductPayload::make($item))
                ->values()
                ->all(),
            'similar_products' => $similarProducts
                ->map(fn (JewelleryProduct $item) => JewelleryProductPayload::make($item))
                ->values()
                ->all(),
        ]);
    }

    private function recordProductView(User $user, JewelleryProduct $product): void
    {
        JewelleryProductView::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'jewellery_product_id' => $product->id,
            ],
            [
                'viewed_at' => Carbon::now(),
            ],
        );
    }

    /**
     * @param  list<int>  $excludeIds
     * @param  list<int>|mixed  $clientIds
     * @return Collection<int, JewelleryProduct>
     */
    private function recentlyViewedProducts(?User $user, array $excludeIds, mixed $clientIds): Collection
    {
        $limit = 8;

        if ($user) {
            $productIds = JewelleryProductView::query()
                ->where('user_id', $user->id)
                ->whereNotIn('jewellery_product_id', $excludeIds)
                ->orderByDesc('viewed_at')
                ->limit($limit)
                ->pluck('jewellery_product_id')
                ->all();

            return $this->productsByOrderedIds($productIds);
        }

        $orderedIds = collect(is_array($clientIds) ? $clientIds : [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0 && ! in_array($id, $excludeIds, true))
            ->unique()
            ->take($limit)
            ->values()
            ->all();

        return $this->productsByOrderedIds($orderedIds);
    }

    /**
     * @param  list<int>  $excludeIds
     * @return Collection<int, JewelleryProduct>
     */
    private function similarProducts(JewelleryProduct $product, array $excludeIds): Collection
    {
        $limit = 8;

        $query = JewelleryProduct::query()
            ->with(['category', 'subCategory'])
            ->where('is_active', true)
            ->whereNotIn('id', $excludeIds)
            ->where('metal_type', $product->metal_type);

        if ($product->jewellery_category_id) {
            $query->where('jewellery_category_id', $product->jewellery_category_id);
        }

        $similar = (clone $query)
            ->when(
                $product->jewellery_sub_category_id,
                fn (Builder $q) => $q->where('jewellery_sub_category_id', $product->jewellery_sub_category_id)
            )
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        if ($similar->count() >= $limit) {
            return $similar;
        }

        $remaining = $limit - $similar->count();
        $alreadyIds = array_merge($excludeIds, $similar->pluck('id')->all());

        $fallback = JewelleryProduct::query()
            ->with(['category', 'subCategory'])
            ->where('is_active', true)
            ->whereNotIn('id', $alreadyIds)
            ->where('metal_type', $product->metal_type)
            ->when(
                $product->jewellery_category_id,
                fn (Builder $q) => $q->where('jewellery_category_id', $product->jewellery_category_id)
            )
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->limit($remaining)
            ->get();

        return $similar->concat($fallback)->values();
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, JewelleryProduct>
     */
    private function productsByOrderedIds(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        $products = JewelleryProduct::query()
            ->with(['category', 'subCategory'])
            ->where('is_active', true)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        return collect($ids)
            ->map(fn (int $id) => $products->get($id))
            ->filter()
            ->values();
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

}
