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
            'reverb.client.host' => 'localhost',
            'reverb.client.port' => 8080,
            'reverb.client.scheme' => 'http',
        ]);

        $this->getJson('/api/v1/rates')
            ->assertOk()
            ->assertJsonPath('data.realtime.enabled', true)
            ->assertJsonPath('data.realtime.channel', 'metal-rates')
            ->assertJsonPath('data.realtime.event', 'rates.updated')
            ->assertJsonPath('data.realtime.key', 'test-key')
            ->assertJsonPath('data.realtime.host', 'localhost')
            ->assertJsonPath('data.realtime.port', 8080);
    }

    public function test_rate_broadcast_dispatches_event(): void
    {
        Event::fake([MetalRatesUpdated::class]);

        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
        ]);

        app(MetalRateService::class)->broadcastCurrentRates();

        Event::assertDispatched(MetalRatesUpdated::class);
    }

    public function test_realtime_config_normalizes_host(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'reverb.client.host' => 'http://hoxtan.developmentalphawizz.com/',
            'reverb.client.port' => 443,
            'reverb.client.scheme' => 'https',
        ]);

        $this->getJson('/api/v1/rates/realtime-config')
            ->assertOk()
            ->assertJsonPath('data.realtime.host', 'hoxtan.developmentalphawizz.com');
    }
}
