<?php

namespace App\Support;

class MetalRateRealtimeConfig
{
    /**
     * @return array<string, mixed>
     */
    public static function make(): array
    {
        $enabled = self::isEnabled();
        $driver = (string) config('broadcasting.default', 'null');
        $connection = config("broadcasting.connections.{$driver}", []);
        $key = is_array($connection) ? ($connection['key'] ?? null) : null;

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

        $client = config('reverb.client', []);
        $scheme = is_array($client) ? ($client['scheme'] ?? 'https') : 'https';

        return array_merge($payload, [
            'key' => $key,
            'host' => self::normalizeHost(is_array($client) ? ($client['host'] ?? null) : null),
            'port' => is_array($client) && isset($client['port']) ? (int) $client['port'] : null,
            'scheme' => $scheme,
            'use_tls' => $scheme === 'https',
            'cluster' => null,
        ]);
    }

    public static function isEnabled(): bool
    {
        $driver = (string) config('broadcasting.default', 'null');

        if (! in_array($driver, ['reverb', 'pusher', 'log'], true)) {
            return false;
        }

        if ($driver === 'log') {
            return true;
        }

        $connection = config("broadcasting.connections.{$driver}", []);

        return is_array($connection) && filled($connection['key'] ?? null);
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
