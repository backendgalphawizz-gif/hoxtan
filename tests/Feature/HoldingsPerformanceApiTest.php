<?php

namespace Tests\Feature;

use App\Models\Investment;
use App\Models\MetalRate;
use App\Models\User;
use App\Services\HoldingsPerformanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HoldingsPerformanceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_performance_points_use_clean_decimals_and_purchase_vs_current_rate(): void
    {
        MetalRate::query()->create([
            'metal_type' => 'gold',
            'rate_per_gram' => 10000,
            'currency' => 'INR',
        ]);

        $user = User::factory()->create([
            'phone' => '9876501100',
            'mpin' => '1234',
            'gold_holdings' => 0.1386,
        ]);

        Investment::query()->create([
            'user_id' => $user->id,
            'metal_type' => 'gold',
            'type' => 'buy',
            'quantity_grams' => 0.1386,
            'remaining_grams' => 0.1386,
            'rate_per_gram' => 14430.01,
            'amount' => 2000,
            'gst_amount' => 0,
            'total_amount' => 2000,
            'status' => 'completed',
            'hold_started_at' => now(),
            'purpose' => 'hold',
        ]);

        $data = app(HoldingsPerformanceService::class)->performance($user, 'gold', 12);
        $latest = collect($data['chart']['points'])->last();

        $this->assertSame(0.1386, $latest['grams']);
        $this->assertSame(2000.0, $latest['purchase_amount']);
        // Only convert: 0.1386 × 10000 current rate.
        $this->assertSame(1386.0, $latest['current_rate_amount']);
        $this->assertSame('₹2,000.00', $latest['purchase_amount_display']);
        $this->assertSame('₹1,386.00', $latest['current_rate_amount_display']);
        $this->assertSame(10000.0, $latest['current_rate']);

        $json = json_encode($latest);
        $this->assertStringNotContainsString('999999', $json);
        $this->assertStringContainsString('"grams":0.1386', $json);
        $this->assertStringContainsString('"purchase_amount":2000', $json);
    }
}
