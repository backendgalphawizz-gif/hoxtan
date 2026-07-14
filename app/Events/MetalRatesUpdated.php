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
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return array_merge($this->rates, [
            'replace' => true,
            'message' => 'Overwrite previous rates. Do not append. Recalculate assets = holdings_grams × rate_per_gram.',
            'withdraw_assets' => WithdrawAssetsBroadcastPayload::fromRates($this->rates),
        ]);
    }
}
