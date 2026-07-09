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

    return [
      'appName' => $settings->get('app_name', config('app_content.app_name', 'HOXTAN')),
      'supportEmail' => $settings->get('support_email', 'support@hoxtan.com'),
      'supportPhone' => $settings->get('support_phone', ''),
      'socialLinks' => self::socialLinks(),
      'websitePages' => self::publishedPages(),
    ];
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
