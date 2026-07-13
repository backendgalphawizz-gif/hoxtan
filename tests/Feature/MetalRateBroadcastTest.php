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
            ->assertJsonPath('data.realtime.port', 8080)
            ->assertJsonPath('data.realtime.websocket_url', 'ws://localhost:8080/app/test-key?protocol=7&client=js&version=8.4.0&flash=false');
    }

    public function test_realtime_config_includes_websocket_url_and_rates(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'reverb.client.host' => 'localhost',
            'reverb.client.port' => 8080,
            'reverb.client.scheme' => 'http',
        ]);

        $this->getJson('/api/v1/rates/realtime-config')
            ->assertOk()
            ->assertJsonPath('data.realtime.websocket_url', 'ws://localhost:8080/app/test-key?protocol=7&client=js&version=8.4.0&flash=false')
            ->assertJsonPath('data.realtime.channel', 'metal-rates')
            ->assertJsonPath('data.realtime.event', 'rates.updated')
            ->assertJsonStructure([
                'data' => [
                    'rates' => [
                        'currency',
                        'unit',
                        'gold',
                        'silver',
                    ],
                ],
            ]);
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
            ->assertJsonPath('data.realtime.host', 'hoxtan.developmentalphawizz.com')
            ->assertJsonPath('data.realtime.websocket_url', 'wss://hoxtan.developmentalphawizz.com/app/test-key?protocol=7&client=js&version=8.4.0&flash=false');
    }

    public function test_app_config_includes_metal_rates_realtime(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'reverb.client.host' => 'localhost',
            'reverb.client.port' => 8080,
            'reverb.client.scheme' => 'http',
        ]);

        $this->getJson('/api/v1/app/config')
            ->assertOk()
            ->assertJsonPath('data.metal_rates_realtime.websocket_url', 'ws://localhost:8080/app/test-key?protocol=7&client=js&version=8.4.0&flash=false')
            ->assertJsonPath('data.metal_rates_realtime.channel', 'metal-rates');
    }

    public function test_misconfigured_pusher_does_not_break_driver_deliveries_api(): void
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher.key' => null,
            'broadcasting.connections.pusher.secret' => null,
            'broadcasting.connections.pusher.app_id' => null,
        ]);

        $this->refreshApplication();

        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher.key' => null,
            'broadcasting.connections.pusher.secret' => null,
            'broadcasting.connections.pusher.app_id' => null,
            'otp.expose_in_response' => true,
        ]);

        $driver = \App\Models\Driver::query()->create([
            'name' => 'Pickup Driver',
            'phone' => '9876543999',
            'vehicle_type' => 'bike',
            'is_active' => true,
        ]);

        $send = $this->postJson('/api/v1/driver/login/send-otp', [
            'phone' => $driver->phone,
        ]);

        $verify = $this->postJson('/api/v1/driver/login/verify-otp', [
            'phone' => $driver->phone,
            'otp' => $send->json('data.otp'),
        ]);

        $this->getJson('/api/v1/driver/deliveries?type=pickup', [
            'Authorization' => 'Bearer '.$verify->json('data.token'),
        ])->assertOk();
    }
}
