<?php

namespace App\Support;

use App\Models\Faq;
use App\Services\AppSettingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DriverAppConfigPayload
{
    public static function make(AppSettingService $settings): array
    {
        return [
            'app' => [
                'name' => config('driver.app_name', 'HOXTAN Driver'),
            ],
            'login' => config('driver.login', []),
            'vehicle_types' => config('driver.vehicle_types', []),
            'otp_length' => (int) config('otp.length', 4),
            'otp_resend_after_seconds' => (int) config('otp.resend_after_seconds', 30),
            'otp_expires_in_seconds' => (int) config('otp.expires_in_seconds', 300),
            'country_code' => '+91',
            'privacy' => LegalPagePayload::make('driver_privacy', $settings),
            'terms' => LegalPagePayload::make('driver_terms', $settings),
            'play_store' => self::playStoreUrls(),
            'support' => [
                'email' => $settings->get('support_email', config('app_content.website_support.email')),
                'phone' => $settings->get('support_phone'),
            ],
        ];
    }

    /**
     * @return array{
     *     privacy_policy_url: string,
     *     terms_url: string,
     *     privacy_policy_embed_url: string,
     *     terms_embed_url: string
     * }
     */
    public static function playStoreUrls(): array
    {
        $playStore = config('app_content.driver_play_store', []);

        return [
            'privacy_policy_url' => url($playStore['privacy_policy_url'] ?? '/driver-privacy-policy'),
            'terms_url' => url($playStore['terms_url'] ?? '/driver-terms-and-conditions'),
            'privacy_policy_embed_url' => url($playStore['privacy_policy_embed_url'] ?? '/embed/driver-privacy-policy'),
            'terms_embed_url' => url($playStore['terms_embed_url'] ?? '/embed/driver-terms-and-conditions'),
        ];
    }
}
