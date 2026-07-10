<?php

namespace App\Support;

use App\Models\StaticPage;
use App\Services\AppSettingService;

class WebsiteViewData
{
  /**
   * @return array<string, mixed>
   */
  public static function shared(): array
  {
    $settings = app(AppSettingService::class);
    $support = config('app_content.website_support', []);
    $supportEmail = (string) ($support['email'] ?? 'support@hoxtandigigold.com');
    $supportPhone = (string) ($support['toll_free'] ?? '18005693934');

    return [
      'appName' => $settings->get('app_name', config('app_content.app_name', 'HOXTAN')),
      'supportEmail' => $supportEmail,
      'supportPhone' => $supportPhone,
      'supportPhoneFormatted' => self::formatSupportPhone($supportPhone),
      'socialLinks' => self::socialLinks(),
      'websitePages' => self::publishedPages(),
    ];
  }

  protected static function formatSupportPhone(string $phone): string
  {
    $digits = preg_replace('/\D/', '', $phone) ?? '';

    if (strlen($digits) === 11 && str_starts_with($digits, '1800')) {
      return substr($digits, 0, 4).' '.substr($digits, 4, 3).' '.substr($digits, 7);
    }

    return $phone;
  }

  /**
   * @return list<array{key: string, slug: string, label: string, url: string, title: string}>
   */
  public static function publishedPages(): array
  {
    $definitions = config('app_content.website_pages', []);
    $slugs = collect($definitions)->pluck('slug')->filter()->all();

    if ($slugs === []) {
      return [];
    }

    $published = StaticPage::query()
      ->whereIn('slug', $slugs)
      ->where('is_published', true)
      ->get()
      ->keyBy('slug');

    return collect($definitions)
      ->filter(fn (array $definition): bool => $published->has($definition['slug']))
      ->map(function (array $definition) use ($published): array {
        $record = $published->get($definition['slug']);

        return [
          'key' => $definition['key'],
          'slug' => $definition['slug'],
          'label' => $definition['label'],
          'url' => url('/'.$definition['slug']),
          'title' => $record->title,
        ];
      })
      ->values()
      ->all();
  }

  /**
   * @return list<array{name: string, url: string, icon: string}>
   */
  public static function socialLinks(): array
  {
    return [
      ['name' => 'Facebook', 'url' => 'https://facebook.com/hoxtan', 'icon' => 'facebook'],
      ['name' => 'Instagram', 'url' => 'https://instagram.com/hoxtan', 'icon' => 'instagram'],
      ['name' => 'X', 'url' => 'https://x.com/hoxtan', 'icon' => 'x'],
      ['name' => 'LinkedIn', 'url' => 'https://linkedin.com/company/hoxtan', 'icon' => 'linkedin'],
      ['name' => 'YouTube', 'url' => 'https://youtube.com/@hoxtan', 'icon' => 'youtube'],
    ];
  }
}
