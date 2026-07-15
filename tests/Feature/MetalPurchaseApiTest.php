<?php

namespace Tests\Feature;

use App\Models\Investment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MetalPurchaseApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_buy_metal_config_returns_screen_data(): void
    {
        $user = User::factory()->create([
            'wallet_balance' => 5000,
            'gold_holdings' => 1.5,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/buy-metal/config')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Buy Gold & Silver')
            ->assertJsonStructure([
                'data' => [
                    'input_modes',
                    'metal_types',
                    'preset_amounts',
                    'rates',
                    'wallet_balance',
                    'gold_holdings',
                ],
            ]);
    }

    public function test_estimate_by_currency_returns_estimated_asset_quantity_for_24k_gold(): void
    {
        $user = User::factory()->create(['wallet_balance' => 10000]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/buy-metal/estimate', [
            'metal_type' => 'gold',
            'input_mode' => 'currency',
            'amount' => 1000,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.estimate.metal_type', 'gold')
            ->assertJsonPath('data.estimate.input_mode', 'currency')
            ->assertJsonPath('data.estimate.purity', '24K')
            ->assertJsonPath('data.estimate.amount', 1000)
            ->assertJsonPath('data.estimate.gst_included', true)
            ->assertJsonStructure([
                'data' => [
                    'estimate' => [
                        'weight_grams',
                        'estimated_asset_quantity' => ['value', 'unit', 'label', 'display'],
                        'rate_per_gram',
                        'total_amount',
                        'wallet_balance',
                        'can_purchase',
                    ],
                ],
            ]);

        $this->assertGreaterThan(0, $response->json('data.estimate.weight_grams'));
    }

    public function test_estimate_by_weight_returns_payable_total(): void
    {
        $user = User::factory()->create(['wallet_balance' => 100000]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/buy-metal/estimate', [
            'metal_type' => 'silver',
            'input_mode' => 'weight',
            'weight_grams' => 10,
        ])
            ->assertOk()
            ->assertJsonPath('data.estimate.metal_type', 'silver')
            ->assertJsonPath('data.estimate.input_mode', 'weight')
            ->assertJsonPath('data.estimate.weight_grams', 10)
            ->assertJsonPath('data.estimate.gst_included', false)
            ->assertJsonStructure([
                'data' => [
                    'estimate' => ['taxable_amount', 'gst_amount', 'total_amount'],
                ],
            ]);
    }

    public function test_user_can_purchase_gold_from_wallet(): void
    {
        $user = User::factory()->create([
            'wallet_balance' => 10000,
            'gold_holdings' => 0,
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/buy-metal/purchase', [
            'metal_type' => 'gold',
            'input_mode' => 'currency',
            'amount' => 1000,
            'payment_method' => 'wallet',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.purchase.metal_type', 'gold')
            ->assertJsonPath('data.purchase.type', 'buy')
            ->assertJsonPath('data.purchase.status', 'completed')
            ->assertJsonPath('data.success.title', 'Purchase Successful');

        $user->refresh();

        $this->assertGreaterThan(0, (float) $user->gold_holdings);
        $this->assertSame(1, Investment::query()->where('user_id', $user->id)->count());
        $this->assertDatabaseCount('holding_certificates', 1);
        $this->assertNotNull($response->json('data.certificate.certificate_number'));
        $this->assertSame('24K', $response->json('data.certificate.purity'));
        $this->assertStringContainsString('HXT-POH-', (string) $response->json('data.certificate.certificate_number'));
    }

    public function test_purchase_fails_when_wallet_balance_is_insufficient(): void
    {
        $user = User::factory()->create(['wallet_balance' => 50]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/buy-metal/purchase', [
            'metal_type' => 'gold',
            'input_mode' => 'currency',
            'amount' => 1000,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['wallet_balance']);
    }
}
