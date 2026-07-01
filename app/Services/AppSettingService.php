<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class AppSettingService
{
  public function get(string $key, mixed $default = null): mixed
  {
    $settings = $this->all();

    return Arr::get($settings, $key, $default);
  }

  public function getFloat(string $key, float $default = 0): float
  {
    return (float) $this->get($key, $default);
  }

  public function getInt(string $key, int $default = 0): int
  {
    return (int) $this->get($key, $default);
  }

  public function getBool(string $key, bool $default = false): bool
  {
    $value = $this->get($key, $default);

    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
  }

  public function gstRate(): float
  {
    return $this->getFloat('gst_rate_percent', 3) / 100;
  }

  public function gstRatePercent(): float
  {
    return $this->getFloat('gst_rate_percent', 3);
  }

  public function all(): array
  {
    return Cache::remember('app_settings', 300, function (): array {
      return AppSetting::query()
        ->pluck('value', 'key')
        ->all();
    });
  }

  public function definitions(): array
  {
    return config('app_settings.definitions', []);
  }

  public function set(string $key, mixed $value): void
  {
    $definition = $this->definitions()[$key] ?? null;

    AppSetting::updateOrCreate(
      ['key' => $key],
      [
        'value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
        'group' => $definition['group'] ?? 'general',
        'label' => $definition['label'] ?? ucfirst(str_replace('_', ' ', $key)),
        'type' => $definition['type'] ?? 'text',
        'description' => $definition['description'] ?? null,
      ],
    );

    $this->forgetCache();
  }

  public function setMany(array $values): void
  {
    foreach ($values as $key => $value) {
      $this->set($key, $value);
    }
  }

  public function forgetCache(): void
  {
    Cache::forget('app_settings');
  }
}
