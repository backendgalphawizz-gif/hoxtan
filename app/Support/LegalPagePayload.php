<?php

namespace App\Support;

use App\Models\StaticPage;
use App\Services\AppSettingService;

class LegalPagePayload
{
    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function make(string $configKey, ?AppSettingService $settings = null): array
    {
        $config = config("app_content.{$configKey}", []);
        $page = self::resolvePage($config);

        return self::format($configKey, $config, $page, $settings);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function resolvePage(array $config): ?StaticPage
    {
        $slugs = array_values(array_filter([
            $config['slug'] ?? null,
            $config['fallback_slug'] ?? null,
        ]));

        foreach ($slugs as $slug) {
            $page = StaticPage::query()
                ->where('slug', $slug)
                ->where('is_published', true)
                ->first();

            if ($page) {
                return $page;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected static function format(
        string $configKey,
        array $config,
        ?StaticPage $page,
        ?AppSettingService $settings = null,
    ): array {
        $resolvedSlug = $page?->slug ?? ($config['slug'] ?? null);

        $payload = array_merge($config, [
            'slug' => $resolvedSlug,
            'title' => $page?->title ?? ($config['title'] ?? null),
            'content' => $page?->content,
            'updated_at' => $page?->updated_at?->toIso8601String(),
        ]);

        if (! empty($config['url_path'])) {
            $payload['url'] = url($config['url_path']);
        }

        if (! empty($config['embed_url_path'])) {
            $payload['embed_url'] = url($config['embed_url_path']);
        }

        if ($configKey === 'user_privacy' && $settings) {
            $payload['privacy_support_email'] = $settings->get(
                'support_email',
                $config['privacy_support_email'] ?? 'privacy@hoxtan.com',
            );
        }

        if ($configKey === 'delete_account' && $settings) {
            $payload['support_email'] = $settings->get(
                'support_email',
                $config['support_email'] ?? 'support@hoxtandigigold.com',
            );
        }

        return $payload;
    }
}
