<?php

namespace App\Events;

use App\Support\MetalRateRealtimeConfig;
use App\Support\WithdrawAssetsBroadcastPayload;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

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
     * Overwrite-friendly payload for mobile.
     * Mobile must REPLACE previous rates state — never append into a list.
     *
     * Note (Pusher protocol): the wire frame is always
     *   { "event": "rates.updated", "data": "<JSON STRING>", "channel": "metal-rates" }
     * Parse `data` once with jsonDecode to get this object.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $payload = array_merge($this->rates, [
            'replace' => true,
            'message' => 'Overwrite previous rates only. Do NOT replace user gold/silver grams or wallet_balance from this public event. Recalculate wallet_amount = cached_grams × rate_per_gram. After buy/sell, listen to private user.{id} event assets.updated or refresh POST /api/v1/rates/push with Bearer token.',
            'withdraw_assets' => WithdrawAssetsBroadcastPayload::fromRates($this->rates),
            // Rate-only — never send null grams here (mobile was wiping wallet after purchase).
            'assets_rates' => [
                'gold' => data_get($this->rates, 'gold.rate_per_gram'),
                'silver' => data_get($this->rates, 'silver.rate_per_gram'),
            ],
            'data_format' => [
                'wire' => 'pusher',
                'data_is_json_string' => true,
                'instruction' => 'Parse message.data once. Update rates from payload.gold/silver. Keep local assets.grams + wallet_balance. value = grams × rate_per_gram.',
            ],
        ]);

        return $this->normalizeForJson($payload);
    }

    /**
     * Clean float encoding (avoid 178.669999...) for the Pusher data JSON string.
     *
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
