<?php

namespace App\Events;

use App\Support\MetalRateRealtimeConfig;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Compact public rates event — keep under Pusher payload limits (~10KB).
 */
class MetalRatesUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $rates
     */
    public function __construct(public array $rates) {}

    public function broadcastWhen(): bool
    {
        return MetalRateRealtimeConfig::isEnabled();
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel((string) config('metal_rates.broadcast_channel', 'metal-rates')),
        ];
    }

    public function broadcastAs(): string
    {
        return (string) config('metal_rates.broadcast_event', 'rates.updated');
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $gold = round((float) data_get($this->rates, 'gold.rate_per_gram', 0), 2);
        $silver = round((float) data_get($this->rates, 'silver.rate_per_gram', 0), 2);

        $payload = [
            'replace' => true,
            'replace_rates_only' => true,
            'currency' => 'INR',
            'unit' => 'gram',
            'fetched_at' => data_get($this->rates, 'fetched_at'),
            'gold' => [
                'metal_type' => 'gold',
                'rate_per_gram' => $gold,
                'change_percent' => data_get($this->rates, 'gold.change_percent'),
                'change_direction' => data_get($this->rates, 'gold.change_direction'),
            ],
            'silver' => [
                'metal_type' => 'silver',
                'rate_per_gram' => $silver,
                'change_percent' => data_get($this->rates, 'silver.change_percent'),
                'change_direction' => data_get($this->rates, 'silver.change_direction'),
            ],
            'assets_rates' => [
                'gold' => $gold,
                'silver' => $silver,
            ],
            'withdraw_assets' => [
                'replace_rates_only' => true,
                'rates' => [
                    'gold' => $gold,
                    'silver' => $silver,
                ],
                'assets' => [
                    ['value' => 'gold', 'rate_per_gram' => $gold],
                    ['value' => 'silver', 'rate_per_gram' => $silver],
                    ['value' => 'sig', 'rate_per_gram' => $gold],
                ],
            ],
        ];

        return $this->normalizeForJson($payload);
    }

    /**
     * Safe dispatch — never break HTTP if Pusher rejects payload.
     *
     * @param  array<string, mixed>  $rates
     */
    public static function dispatchSafe(array $rates): void
    {
        try {
            event(new static($rates));
        } catch (BroadcastException|\Throwable $e) {
            Log::warning('MetalRatesUpdated broadcast skipped', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeForJson(array $payload): array
    {
        $previous = ini_get('serialize_precision');
        ini_set('serialize_precision', '-1');

        try {
            /** @var array<string, mixed> $normalized */
            $normalized = json_decode(
                json_encode($payload, JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

            return $normalized;
        } finally {
            if ($previous !== false) {
                ini_set('serialize_precision', (string) $previous);
            }
        }
    }
}
