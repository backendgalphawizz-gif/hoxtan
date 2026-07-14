<?php

namespace App\Support;

class MetalRateRealtimeConfig
{
    /**
     * Connection details for the mobile app.
     * Rates are delivered only over this WebSocket — do not poll GET /rates.
     *
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
            'websocket_url' => null,
            'key' => null,
            'host' => null,
            'port' => null,
            'scheme' => null,
            'use_tls' => false,
            'cluster' => null,
            'broadcast_interval_seconds' => (int) config('metal_rates.broadcast_interval_seconds', 60),
            'instructions' => [
                'Connect to websocket_url (Pusher protocol).',
                'After pusher:connection_established, send subscribe JSON (see subscribe_message).',
                'Listen for rates.updated — payload has replace:true. OVERWRITE previous rates object; do not append {},{},{}.',
                'Wallet / total assets: load once from GET /api/v1/profile/assets (grams + wallet). On each rates.updated, overwrite rates and recalculate values = grams × new rate.',
                'Withdraw screen: load once from GET /api/v1/withdraw/assets. On each rates.updated, overwrite withdraw_assets (same shape), keep available_grams/bank from cache, recalculate available_value = available_grams × rate_per_gram.',
                'Do not call GET /api/v1/rates for live prices; use this socket only.',
            ],
            'withdraw_assets_api' => '/api/v1/withdraw/assets',
            'subscribe_message' => [
                'event' => 'pusher:subscribe',
                'data' => [
                    'auth' => '',
                    'channel' => (string) config('metal_rates.broadcast_channel', 'metal-rates'),
                ],
            ],
        ];

        if (! $enabled || ! is_array($connection) || blank($key)) {
            return $payload;
        }

        $client = config('reverb.client', []);
        $scheme = is_array($client) ? (string) ($client['scheme'] ?? 'https') : 'https';
        $host = self::normalizeHost(is_array($client) ? ($client['host'] ?? null) : null);
        $port = is_array($client) && isset($client['port']) ? (int) $client['port'] : null;

        return array_merge($payload, [
            'key' => $key,
            'host' => $host,
            'port' => $port,
            'scheme' => $scheme,
            'use_tls' => $scheme === 'https',
            'cluster' => null,
            'websocket_url' => self::buildWebsocketUrl($scheme, $host, $port, (string) $key),
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

    protected static function buildWebsocketUrl(?string $scheme, ?string $host, ?int $port, string $key): ?string
    {
        if (blank($host) || blank($key)) {
            return null;
        }

        $wsScheme = ($scheme === 'https') ? 'wss' : 'ws';
        $defaultPort = $wsScheme === 'wss' ? 443 : 80;
        $authority = $host;

        if ($port !== null && $port !== $defaultPort) {
            $authority .= ":{$port}";
        }

        return "{$wsScheme}://{$authority}/app/{$key}?protocol=7&client=js&version=8.4.0&flash=false";
    }

    protected static function normalizeHost(?string $host): ?string
    {
        if (blank($host)) {
            return null;
        }

        $host = trim($host, " \t\n\r\0\x0B\"'");

        if (str_contains($host, '://')) {
            $parsed = parse_url($host, PHP_URL_HOST);

            if (is_string($parsed) && filled($parsed)) {
                $host = $parsed;
            }
        }

        return rtrim($host, '/');
    }
}
