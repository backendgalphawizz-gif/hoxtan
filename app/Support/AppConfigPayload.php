<?php

namespace App\Support;

use App\Models\Faq;
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

        return [
            'app' => [
                'name' => $settings->get('app_name', config('app_content.app_name', 'HOXTAN')),
            ],
            'play_store' => self::playStoreUrls(),
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
            'terms' => self::userTerms($settings),
            'privacy' => self::userPrivacy($settings),
            'driver_privacy' => self::driverPrivacy($settings),
            'driver_terms' => self::driverTerms($settings),
            'driver' => self::driverLegal($settings),
            'delete_account' => self::deleteAccount($settings),
            'metal_rates_realtime' => MetalRateRealtimeConfig::make(),
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

    protected static function userTerms(AppSettingService $settings): array
    {
        return array_merge(
            config('app_content.terms', []),
            LegalPagePayload::make('user_terms', $settings),
        );
    }

    protected static function userPrivacy(AppSettingService $settings): array
    {
        return array_merge(
            config('app_content.privacy', []),
            LegalPagePayload::make('user_privacy', $settings),
        );
    }

    protected static function driverPrivacy(AppSettingService $settings): array
    {
        return LegalPagePayload::make('driver_privacy', $settings);
    }

    protected static function driverTerms(AppSettingService $settings): array
    {
        return LegalPagePayload::make('driver_terms', $settings);
    }

    /**
     * @return array<string, mixed>
     */
    protected static function driverLegal(AppSettingService $settings): array
    {
        return [
            'privacy' => self::driverPrivacy($settings),
            'terms' => self::driverTerms($settings),
            'play_store' => DriverAppConfigPayload::playStoreUrls(),
        ];
    }

    protected static function deleteAccount(AppSettingService $settings): array
    {
        return LegalPagePayload::make('delete_account', $settings);
    }

    /**
     * @return array{
     *     privacy_policy_url: string,
     *     delete_account_url: string,
     *     privacy_policy_embed_url: string,
     *     delete_account_embed_url: string,
     *     terms_url: string,
     *     terms_embed_url: string
     * }
     */
    public static function playStoreUrls(): array
    {
        $playStore = config('app_content.play_store', []);
        $userPlayStore = config('app_content.user_play_store', []);

        return [
            'privacy_policy_url' => url($userPlayStore['privacy_policy_url'] ?? $playStore['privacy_policy_url'] ?? '/user-privacy-policy'),
            'terms_url' => url($userPlayStore['terms_url'] ?? '/user-terms-and-conditions'),
            'delete_account_url' => url($playStore['delete_account_url'] ?? '/delete-account'),
            'privacy_policy_embed_url' => url($userPlayStore['privacy_policy_embed_url'] ?? $playStore['privacy_policy_embed_url'] ?? '/embed/user-privacy-policy'),
            'terms_embed_url' => url($userPlayStore['terms_embed_url'] ?? '/embed/user-terms-and-conditions'),
            'delete_account_embed_url' => url($playStore['delete_account_embed_url'] ?? '/embed/delete-account'),
            'driver_privacy_policy_url' => url(config('app_content.driver_play_store.privacy_policy_url', '/driver-privacy-policy')),
            'driver_terms_url' => url(config('app_content.driver_play_store.terms_url', '/driver-terms-and-conditions')),
            'driver_privacy_policy_embed_url' => url(config('app_content.driver_play_store.privacy_policy_embed_url', '/embed/driver-privacy-policy')),
            'driver_terms_embed_url' => url(config('app_content.driver_play_store.terms_embed_url', '/embed/driver-terms-and-conditions')),
        ];
    }
}
