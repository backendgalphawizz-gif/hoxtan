<?php

namespace App\Events;

use App\Support\MetalRateRealtimeConfig;
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
     * Same shape as the old GET /api/v1/rates `data` payload (without realtime).
     * Mobile should read gold/silver from this event only.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->rates;
    }
}
