<?php

namespace App\Support;

class AssetUrl
{
    /**
     * Application base URL without trailing slash.
     * Uses the current HTTP request origin when available (LAN IP, port, etc.),
     * otherwise falls back to APP_URL from .env.
     */
    public static function base(): string
    {
        if (! app()->runningInConsole() && app()->bound('request')) {
            $host = request()->getSchemeAndHttpHost();

            if (filled($host)) {
                return rtrim($host, '/');
            }
        }

        $configured = rtrim((string) config('app.url'), '/');

        return $configured !== '' ? $configured : 'http://localhost';
    }

    /**
     * Full public storage URL for a file path under storage/app/public.
     */
    public static function publicStorage(mixed $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (is_array($path)) {
            $path = $path[0] ?? null;
        }

        if (blank($path) || ! is_string($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return static::base().'/storage/'.ltrim($path, '/');
    }
}
