<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\StaticPage;
use App\Services\AppSettingService;
use App\Support\ApiResponse;
use App\Support\AppConfigPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppConfigController extends Controller
{
    public function index(AppSettingService $settings): JsonResponse
    {
        return ApiResponse::success(AppConfigPayload::make($settings));
    }

    public function faqs(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', Rule::in(
                collect(config('app_content.faq_categories', []))->pluck('value')->all()
            )],
        ]);

        $query = Faq::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id');

        if (filled($data['category'] ?? null)) {
            $query->where('category', $data['category']);
        }

        if (filled($data['search'] ?? null)) {
            $search = $data['search'];
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('question', 'like', '%'.$search.'%')
                    ->orWhere('answer', 'like', '%'.$search.'%');
            });
        }

        $faqs = $query->get();

        return ApiResponse::success([
            'faqs_screen' => config('app_content.faqs_screen', []),
            'faq_categories' => config('app_content.faq_categories', []),
            'faqs' => AppConfigPayload::faqCollection($faqs),
        ]);
    }

    public function page(string $slug): JsonResponse
    {
        $page = StaticPage::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->first();

        if (! $page) {
            return ApiResponse::error('Page not found.', [], 404);
        }

        $payload = [
            'slug' => $page->slug,
            'title' => $page->title,
            'content' => $page->content,
            'updated_at' => $page->updated_at?->toIso8601String(),
        ];

        if ($slug === config('app_content.terms.slug')) {
            $payload = array_merge(config('app_content.terms', []), $payload);
        }

        if ($slug === config('app_content.privacy.slug')) {
            $payload = array_merge(config('app_content.privacy', []), $payload);
        }

        return ApiResponse::success([
            'page' => $payload,
        ]);
    }
}
