<?php

namespace Tests\Unit;

use App\Services\AppSettingService;
use App\Services\MetalsApiService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetalsApiServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(AppSettingService::class, function ($mock): void {
            $mock->shouldReceive('get')->andReturn(null);
            $mock->shouldReceive('getInt')->with('metal_api_timeout_seconds', 10)->andReturn(10);
        });
    }

    public function test_gold_falls_back_to_latest_usd_symbol_price(): void
    {
        config([
            'services.metals_api.key' => 'test-key',
            'services.metals_api.gold_symbol' => 'VISA-24k',
        ]);

        Http::fake([
            '*/gold-price-india*' => Http::response([
                'success' => false,
                'error' => ['type' => 'invalid_plan_gold_india'],
            ], 400),
            '*/latest*' => Http::response([
                'success' => true,
                'rates' => [
                    'VISA-24k' => 6.9228106611284e-5,
                    'USDVISA-24k' => 14445.0,
                ],
            ], 200),
        ]);

        $rate = app(MetalsApiService::class)->fetchGoldRatePerGram();

        $this->assertSame(14445.0, $rate);
    }

    public function test_silver_uses_latest_inr_per_gram(): void
    {
        config(['services.metals_api.key' => 'test-key']);

        Http::fake([
            '*/latest*' => Http::response([
                'success' => true,
                'base' => 'INR',
                'rates' => [
                    'XAG' => 183.5146323565,
                ],
            ], 200),
        ]);

        $rate = app(MetalsApiService::class)->fetchSilverRatePerGram();

        $this->assertSame(183.51, $rate);
    }
}
