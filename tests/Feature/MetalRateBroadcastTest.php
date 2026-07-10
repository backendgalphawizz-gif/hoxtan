<?php

namespace Tests\Feature;

use App\Events\MetalRatesUpdated;
use App\Services\MetalRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class MetalRateBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_rates_api_includes_realtime_config(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.options.host' => 'localhost',
            'broadcasting.connections.reverb.options.port' => 8080,
            'broadcasting.connections.reverb.options.scheme' => 'http',
            'broadcasting.connections.reverb.options.useTLS' => false,
        ]);

        $this->getJson('/api/v1/rates')
            ->assertOk()
            ->assertJsonPath('data.realtime.enabled', true)
            ->assertJsonPath('data.realtime.channel', 'metal-rates')
            ->assertJsonPath('data.realtime.event', 'rates.updated')
            ->assertJsonPath('data.realtime.key', 'test-key');
    }

    public function test_manual_rate_update_broadcasts_event(): void
    {
        Event::fake([MetalRatesUpdated::class]);

        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
        ]);

        app(MetalRateService::class)->applyManualRate('gold', 7300.50, true, 'Test override');

        Event::assertDispatched(MetalRatesUpdated::class, function (MetalRatesUpdated $event): bool {
            return ($event->rates['gold']['rate_per_gram'] ?? null) === 7300.50;
        });
    }

    public function test_realtime_config_endpoint(): void
    {
        $this->getJson('/api/v1/rates/realtime-config')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'realtime' => [
                        'enabled',
                        'driver',
                        'channel',
                        'event',
                        'fallback_poll_seconds',
                    ],
                ],
            ]);
    }
}
