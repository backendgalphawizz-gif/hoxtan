<?php

namespace App\Support;

use App\Models\Faq;
use App\Models\StaticPage;
use App\Services\AppSettingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AppConfigPayload
{
    public static function make(AppSettingService $settings): array
    {
        $faqs = Faq::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $termsPage = StaticPage::query()
            ->where('slug', config('app_content.terms.slug', 'terms-and-conditions'))
            ->where('is_published', true)
            ->first();

        $privacyPage = StaticPage::query()
            ->where('slug', config('app_content.privacy.slug', 'privacy-policy'))
            ->where('is_published', true)
            ->first();

        return [
            'app' => [
                'name' => $settings->get('app_name', config('app_content.app_name', 'HOXTAN')),
            ],
            'faqs_screen' => config('app_content.faqs_screen', []),
            'faq_categories' => config('app_content.faq_categories', []),
            'faqs' => self::faqCollection($faqs),
            'concierge' => array_merge(
                config('app_content.concierge', []),
                self::customerCare($settings),
            ),
            'support' => [
                'categories' => config('support.categories', []),
                'status_filters' => config('support.filters', []),
                'customer_care' => self::customerCare($settings),
            ],
            'terms' => self::terms($termsPage),
            'privacy' => self::privacy($privacyPage, $settings),
        ];
    }

    /**
     * @param  Collection<int, Faq>  $faqs
     */
    public static function faqCollection(Collection $faqs): array
    {
        return $faqs
            ->map(fn (Faq $faq) => self::faq($faq))
            ->values()
            ->all();
    }

    public static function faq(Faq $faq): array
    {
        return [
            'id' => $faq->id,
            'category' => $faq->category,
            'category_label' => self::faqCategoryLabel($faq->category),
            'question' => $faq->question,
            'answer' => $faq->answer,
            'answer_preview' => Str::limit(strip_tags($faq->answer), 160),
            'sort_order' => $faq->sort_order,
        ];
    }

    public static function faqCategoryLabel(string $category): string
    {
        $match = collect(config('app_content.faq_categories', []))
            ->firstWhere('value', $category);

        return $match['label'] ?? Str::upper(str_replace('_', ' ', $category));
    }

    public static function customerCare(AppSettingService $settings): array
    {
        $phone = trim((string) $settings->get('support_phone', ''));

        return [
            'voice_support' => [
                'title' => 'Voice Support',
                'description' => 'Direct line to your dedicated portfolio director for immediate assistance.',
                'phone' => $phone,
                'phone_display' => filled($phone) ? $phone : null,
            ],
            'email_concierge' => [
                'title' => 'Email Concierge',
                'description' => 'Document-heavy inquiries or formal records of strategic decisions.',
                'email' => $settings->get('support_email', 'support@hoxtandigigold.com'),
            ],
            'response_time' => config('support.response_time'),
            'hours' => config('support.support_hours'),
        ];
    }

    protected static function terms(?StaticPage $page): array
    {
        $config = config('app_content.terms', []);

        return array_merge($config, [
            'title' => $page?->title ?? ($config['title'] ?? 'Terms & Conditions'),
            'content' => $page?->content,
            'updated_at' => $page?->updated_at?->toIso8601String(),
        ]);
    }

    protected static function privacy(?StaticPage $page, AppSettingService $settings): array
    {
        $config = config('app_content.privacy', []);

        return array_merge($config, [
            'title' => $page?->title ?? ($config['title'] ?? 'Privacy Policy'),
            'content' => $page?->content,
            'privacy_support_email' => $settings->get('support_email', $config['privacy_support_email'] ?? 'privacy@hoxtan.com'),
            'updated_at' => $page?->updated_at?->toIso8601String(),
        ]);
    }
}
