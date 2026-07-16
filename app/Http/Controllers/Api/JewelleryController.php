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
use App\Support\JewelleryOptions;
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
            ->orderBy('id')
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
            ->orderBy('id')
            ->get()
            ->map(fn (JewellerySubCategory $sub) => $this->subCategoryPayload($sub));

        return ApiResponse::success(['sub_categories' => $subCategories]);
    }

    public function subSubCategories(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sub_category_id' => ['nullable', 'integer', 'exists:jewellery_sub_categories,id'],
            'category_id' => ['nullable', 'integer', 'exists:jewellery_categories,id'],
        ]);

        $subSubCategories = JewellerySubSubCategory::query()
            ->where('is_active', true)
            ->when(
                filled($data['sub_category_id'] ?? null),
                fn (Builder $q) => $q->where('jewellery_sub_category_id', $data['sub_category_id'])
            )
            ->when(
                filled($data['category_id'] ?? null) && blank($data['sub_category_id'] ?? null),
                fn (Builder $q) => $q->whereHas('subCategory', function (Builder $inner) use ($data): void {
                    $inner->where('jewellery_category_id', $data['category_id'])
                        ->where('is_active', true);
                })
            )
            ->orderBy('jewellery_sub_category_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (JewellerySubSubCategory $subSub) => $this->subSubCategoryPayload($subSub, includeParent: true));

        return ApiResponse::success(['sub_sub_categories' => $subSubCategories]);
    }

    public function showSubSubCategory(JewellerySubSubCategory $subSubCategory): JsonResponse
    {
        if (! $subSubCategory->is_active) {
            return ApiResponse::error('Sub sub category not found.', [], 404);
        }

        $subSubCategory->loadMissing('subCategory');

        return ApiResponse::success([
            'sub_sub_category' => $this->subSubCategoryPayload($subSubCategory, includeParent: true),
        ]);
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

    /**
     * Filters sheet config + hierarchy helpers for category → sub → sub-sub.
     */
    public function filters(Request $request): JsonResponse
    {
        $data = $request->validate([
            'metal_type' => ['nullable', Rule::in(['gold', 'silver'])],
            'category_id' => ['nullable', 'integer', 'exists:jewellery_categories,id'],
            'sub_category_id' => ['nullable', 'integer', 'exists:jewellery_sub_categories,id'],
        ]);

        $metalType = $data['metal_type'] ?? null;

        $categories = JewelleryCategory::query()
            ->where('is_active', true)
            ->when(
                filled($metalType),
                fn (Builder $query) => $query->where(function (Builder $inner) use ($metalType): void {
                    $inner->where('metal_type', $metalType)->orWhere('metal_type', 'both');
                })
            )
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (JewelleryCategory $category) => $this->categoryPayload($category))
            ->values()
            ->all();

        $subCategoriesQuery = JewellerySubCategory::query()
            ->where('is_active', true)
            ->when(
                filled($data['category_id'] ?? null),
                fn (Builder $q) => $q->where('jewellery_category_id', $data['category_id'])
            )
            ->when(
                filled($metalType) && blank($data['category_id'] ?? null),
                fn (Builder $q) => $q->whereHas('category', function (Builder $inner) use ($metalType): void {
                    $inner->where('is_active', true)
                        ->where(function (Builder $metal) use ($metalType): void {
                            $metal->where('metal_type', $metalType)->orWhere('metal_type', 'both');
                        });
                })
            )
            ->orderBy('jewellery_category_id')
            ->orderBy('sort_order')
            ->orderBy('id');

        $subCategories = $subCategoriesQuery->get()
            ->map(fn (JewellerySubCategory $sub) => $this->subCategoryPayload($sub, includeParent: true))
            ->values()
            ->all();

        $subSubCategories = [];
        if (filled($data['sub_category_id'] ?? null)) {
            $subSubCategories = JewellerySubSubCategory::query()
                ->where('jewellery_sub_category_id', $data['sub_category_id'])
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (JewellerySubSubCategory $row) => $this->subSubCategoryPayload($row, includeParent: true))
                ->values()
                ->all();
        }

        return ApiResponse::success([
            'title' => 'Filters',
            'search_placeholder' => config('jewellery.search_placeholder'),
            'genders' => config('jewellery.genders', []),
            'purities' => JewelleryOptions::purities($metalType),
            'weight' => config('jewellery.weight', []),
            'budget' => config('jewellery.budget', []),
            'metal_type' => $metalType,
            'hierarchy' => [
                'category' => 'Category (admin) → product chips like Rings / Necklaces',
                'sub_category' => 'Sub Category under a category',
                'sub_sub_category' => 'Sub Sub Category under a sub category',
                'gender' => "Men's / Women's on Filters screen maps to product gender",
            ],
            'categories' => $categories,
            'sub_categories' => $subCategories,
            'sub_sub_categories' => $subSubCategories,
            'apply_endpoint' => '/api/v1/jewellery/products',
            'reset_defaults' => [
                'gender' => null,
                'purity' => null,
                'category_id' => null,
                'sub_category_id' => null,
                'sub_sub_category_id' => null,
                'min_weight' => null,
                'max_weight' => null,
                'min_budget' => null,
                'max_budget' => null,
                'search' => null,
            ],
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        $data = $request->validate([
            'metal_type' => ['nullable', Rule::in(['gold', 'silver'])],
            'gender' => ['nullable', Rule::in(['men', 'women', 'unisex'])],
            'category_id' => ['nullable', 'integer', 'exists:jewellery_categories,id'],
            'sub_category_id' => ['nullable', 'integer', 'exists:jewellery_sub_categories,id'],
            'sub_sub_category_id' => ['nullable', 'integer', 'exists:jewellery_sub_sub_categories,id'],
            'purity' => ['nullable', 'string', 'max:20'],
            'search' => ['nullable', 'string', 'max:120'],
            'q' => ['nullable', 'string', 'max:120'],
            'min_weight' => ['nullable', 'numeric', 'min:0'],
            'max_weight' => ['nullable', 'numeric', 'min:0', 'gte:min_weight'],
            'min_budget' => ['nullable', 'numeric', 'min:0'],
            'max_budget' => ['nullable', 'numeric', 'min:0', 'gte:min_budget'],
        ]);

        $search = trim((string) ($data['search'] ?? $data['q'] ?? ''));

        $query = JewelleryProduct::query()
            ->with(['category', 'subCategory', 'subSubCategory', 'variants'])
            ->where('is_active', true)
            ->when(filled($data['metal_type'] ?? null), fn (Builder $q) => $q->where('metal_type', $data['metal_type']))
            ->when(filled($data['gender'] ?? null), function (Builder $q) use ($data): void {
                $q->where(function (Builder $inner) use ($data): void {
                    $inner->where('gender', $data['gender']);
                    if (in_array($data['gender'], ['men', 'women'], true)) {
                        $inner->orWhere('gender', 'unisex');
                    }
                });
            })
            ->when(filled($data['category_id'] ?? null), fn (Builder $q) => $q->where('jewellery_category_id', $data['category_id']))
            ->when(filled($data['sub_category_id'] ?? null), fn (Builder $q) => $q->where('jewellery_sub_category_id', $data['sub_category_id']))
            ->when(filled($data['sub_sub_category_id'] ?? null), fn (Builder $q) => $q->where('jewellery_sub_sub_category_id', $data['sub_sub_category_id']))
            ->when(filled($data['purity'] ?? null), function (Builder $q) use ($data): void {
                $normalized = strtoupper(str_replace([' ', '.'], '', (string) $data['purity']));
                $q->whereRaw(
                    "REPLACE(REPLACE(UPPER(COALESCE(purity, '')), ' ', ''), '.', '') = ?",
                    [$normalized]
                );
            })
            ->when($search !== '', function (Builder $q) use ($search): void {
                $like = '%'.$search.'%';
                $q->where(function (Builder $inner) use ($like): void {
                    $inner->where('name', 'like', $like)
                        ->orWhere('sku', 'like', $like)
                        ->orWhere('description', 'like', $like);
                });
            })
            ->when(isset($data['min_weight']), fn (Builder $q) => $q->where('weight_grams', '>=', (float) $data['min_weight']))
            ->when(isset($data['max_weight']), fn (Builder $q) => $q->where('weight_grams', '<=', (float) $data['max_weight']))
            ->orderBy('sort_order')
            ->orderByDesc('id');

        $products = $query->get()
            ->map(fn (JewelleryProduct $product) => JewelleryProductPayload::make($product));

        $minBudget = isset($data['min_budget']) ? (float) $data['min_budget'] : null;
        $maxBudget = isset($data['max_budget']) ? (float) $data['max_budget'] : null;

        if ($minBudget !== null || $maxBudget !== null) {
            $products = $products->filter(function (array $row) use ($minBudget, $maxBudget): bool {
                $price = (float) ($row['total_price'] ?? $row['price'] ?? 0);
                if ($minBudget !== null && $price < $minBudget) {
                    return false;
                }
                if ($maxBudget !== null && $price > $maxBudget) {
                    return false;
                }

                return true;
            })->values();
        } else {
            $products = $products->values();
        }

        return ApiResponse::success([
            'products' => $products,
            'filters_applied' => [
                'metal_type' => $data['metal_type'] ?? null,
                'gender' => $data['gender'] ?? null,
                'category_id' => $data['category_id'] ?? null,
                'sub_category_id' => $data['sub_category_id'] ?? null,
                'sub_sub_category_id' => $data['sub_sub_category_id'] ?? null,
                'purity' => $data['purity'] ?? null,
                'search' => $search !== '' ? $search : null,
                'min_weight' => isset($data['min_weight']) ? (float) $data['min_weight'] : null,
                'max_weight' => isset($data['max_weight']) ? (float) $data['max_weight'] : null,
                'min_budget' => $minBudget,
                'max_budget' => $maxBudget,
            ],
            'count' => $products->count(),
        ]);
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

        $product->load(['category', 'subCategory', 'subSubCategory', 'variants']);

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
            ->with(['category', 'subCategory', 'variants'])
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
            ->with(['category', 'subCategory', 'variants'])
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
            ->with(['category', 'subCategory', 'variants'])
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
            'sort_order' => (int) $category->sort_order,
        ];
    }

    /**
     * @return array{id: int, name: string, slug: string, sort_order: int, category_id?: int}
     */
    private function subCategoryPayload(JewellerySubCategory $sub, bool $includeParent = false): array
    {
        $payload = [
            'id' => $sub->id,
            'name' => $sub->name,
            'slug' => $sub->slug,
            'sort_order' => (int) $sub->sort_order,
        ];

        if ($includeParent) {
            $payload['category_id'] = $sub->jewellery_category_id;
        }

        return $payload;
    }

    /**
     * @return array{id: int, name: string, slug: string, sort_order: int, sub_category_id?: int}
     */
    private function subSubCategoryPayload(JewellerySubSubCategory $subSub, bool $includeParent = false): array
    {
        $payload = [
            'id' => $subSub->id,
            'name' => $subSub->name,
            'slug' => $subSub->slug,
            'sort_order' => (int) $subSub->sort_order,
        ];

        if ($includeParent) {
            $payload['sub_category_id'] = $subSub->jewellery_sub_category_id;
        }

        return $payload;
    }

}
