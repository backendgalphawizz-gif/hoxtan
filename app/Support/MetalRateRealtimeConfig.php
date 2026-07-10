<?php

namespace App\Support;

class MetalRateRealtimeConfig
{
    /**
     * @return array<string, mixed>
     */
    public static function make(): array
    {
        $driver = (string) config('broadcasting.default', 'null');
        $connection = config("broadcasting.connections.{$driver}", []);
        $key = is_array($connection) ? ($connection['key'] ?? null) : null;
        $enabled = in_array($driver, ['reverb', 'pusher'], true) && filled($key);

        $payload = [
            'enabled' => $enabled,
            'driver' => $driver,
            'protocol' => 'pusher',
            'channel' => (string) config('metal_rates.broadcast_channel', 'metal-rates'),
            'event' => (string) config('metal_rates.broadcast_event', 'rates.updated'),
            'fallback_poll_seconds' => 45,
        ];

        if (! $enabled || ! is_array($connection)) {
            return $payload;
        }

        $options = is_array($connection['options'] ?? null) ? $connection['options'] : [];

        return array_merge($payload, [
            'key' => $key,
            'host' => self::normalizeHost($options['host'] ?? null),
            'port' => isset($options['port']) ? (int) $options['port'] : null,
            'scheme' => $options['scheme'] ?? 'https',
            'use_tls' => (bool) ($options['useTLS'] ?? ($options['scheme'] ?? 'https') === 'https'),
            'cluster' => $options['cluster'] ?? null,
        ]);
    }

    protected static function normalizeHost(?string $host): ?string
    {
        if (blank($host)) {
            return null;
        }

        $host = trim($host);

        if (str_contains($host, '://')) {
            $parsed = parse_url($host, PHP_URL_HOST);

            if (is_string($parsed) && filled($parsed)) {
                $host = $parsed;
            }
        }

        return rtrim($host, '/');
    }
}
