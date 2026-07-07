<?php

namespace App\Support;

class ReportRegistry
{
    public static function categories(): array
    {
        return config('reports.categories', []);
    }

    public static function categoryMeta(string $key): array
    {
        return config("reports.category_meta.{$key}", [
            'icon' => 'heroicon-o-document-chart-bar',
            'accent' => 'primary',
            'description' => '',
        ]);
    }

    public static function definitions(): array
    {
        return config('reports.definitions', []);
    }

    public static function definition(string $module): ?array
    {
        return static::definitions()[$module] ?? null;
    }

    /**
     * @return array<int, array{module: string, definition: array<string, mixed>}>
     */
    public static function accessibleByCategory(): array
    {
        $grouped = [];

        foreach (static::definitions() as $module => $definition) {
            $page = $definition['page'] ?? null;

            if (($definition['status'] ?? '') === 'link') {
                if (! is_string($page) || ! class_exists($page) || ! $page::canAccess()) {
                    continue;
                }

                $category = $definition['category'] ?? 'users';
                $grouped[$category][] = [
                    'module' => $module,
                    'definition' => $definition,
                    'is_link' => true,
                ];

                continue;
            }

            if (! is_string($page) || ! class_exists($page)) {
                continue;
            }

            if (! $page::canAccess()) {
                continue;
            }

            $category = $definition['category'] ?? 'users';
            $grouped[$category][] = [
                'module' => $module,
                'definition' => $definition,
                'is_link' => false,
            ];
        }

        return $grouped;
    }

    public static function resolveUrl(array $definition): ?string
    {
        $page = $definition['page'] ?? null;

        if (! is_string($page) || ! class_exists($page)) {
            return null;
        }

        return $page::getUrl();
    }
}
