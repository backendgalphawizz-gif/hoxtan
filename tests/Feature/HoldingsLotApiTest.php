<?php

namespace Tests\Feature;

use App\Models\Investment;
use App\Models\MetalRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HoldingsLotApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_purchase_multiple_hold_lots_and_list_them(): void
    {
        MetalRate::query()->create([
            'metal_type' => 'gold',
            'rate_per_gram' => 7000,
            'currency' => 'INR',
        ]);

        $user = User::factory()->create(['phone' => '9876501001', 'mpin' => '1234']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/holdings/purchase', [
            'weight_grams' => 5,
            'amount' => 35000,
            'payment_method' => 'upi',
            'transaction_id' => 'TXN-HOLD-1',
        ])->assertCreated()
            ->assertJsonPath('data.holding.summary.total_lots', 1);

        $this->postJson('/api/v1/holdings/purchase', [
            'weight_grams' => 2,
            'amount' => 14000,
            'payment_method' => 'upi',
            'transaction_id' => 'TXN-HOLD-2',
        ])->assertCreated();

        $list = $this->getJson('/api/v1/holdings?metal_type=gold');

        $list->assertOk()
            ->assertJsonPath('data.summary.total_lots', 2)
            ->assertJsonPath('data.summary.total_grams', 7)
            ->assertJsonStructure([
                'data' => [
                    'lots' => [
                        [
                            'id',
                            'remaining_grams',
                            'hold_started_at_display',
                            'bonus_due_at_display',
                            'bonus_eligible',
                            'can_sell',
                        ],
                    ],
                ],
            ]);

        $this->assertDatabaseHas('investments', [
            'user_id' => $user->id,
            'type' => 'buy',
            'quantity_grams' => 5,
            'remaining_grams' => 5,
            'purpose' => 'hold',
        ]);
        $this->assertDatabaseHas('investments', [
            'user_id' => $user->id,
            'type' => 'buy',
            'quantity_grams' => 2,
            'remaining_grams' => 2,
            'purpose' => 'hold',
        ]);
    }

    public function test_hold_bonus_credits_one_percent_after_one_year_per_lot(): void
    {
        MetalRate::query()->create([
            'metal_type' => 'gold',
            'rate_per_gram' => 8000,
            'currency' => 'INR',
        ]);

        $user = User::factory()->create([
            'phone' => '9876501002',
            'mpin' => '1234',
            'gold_holdings' => 0,
        ]);
        Sanctum::actingAs($user);

        $lotA = Investment::query()->create([
            'user_id' => $user->id,
            'metal_type' => 'gold',
            'type' => 'buy',
            'quantity_grams' => 5,
            'remaining_grams' => 5,
            'rate_per_gram' => 7000,
            'amount' => 35000,
            'gst_amount' => 0,
            'total_amount' => 35000,
            'status' => 'completed',
            'hold_started_at' => now()->subDays(366),
            'purpose' => 'hold',
            'created_at' => now()->subDays(366),
        ]);

        $lotB = Investment::query()->create([
            'user_id' => $user->id,
            'metal_type' => 'gold',
            'type' => 'buy',
            'quantity_grams' => 2,
            'remaining_grams' => 2,
            'rate_per_gram' => 7100,
            'amount' => 14200,
            'gst_amount' => 0,
            'total_amount' => 14200,
            'status' => 'completed',
            'hold_started_at' => now()->subDays(10),
            'purpose' => 'hold',
            'created_at' => now()->subDays(10),
        ]);

        app(\App\Services\UserHoldingsService::class)->recalculateForUser($user->id);

        $this->postJson('/api/v1/holdings/claim-bonus', [
            'lot_id' => $lotA->id,
        ])->assertOk()
            ->assertJsonPath('data.credited_lots', 1)
            ->assertJsonPath('data.bonus_grams', 0.05);

        $this->assertNotNull($lotA->fresh()->hold_bonus_credited_at);
        $this->assertNull($lotB->fresh()->hold_bonus_credited_at);

        $this->assertDatabaseHas('investments', [
            'user_id' => $user->id,
            'purpose' => 'hold_bonus',
            'quantity_grams' => 0.05,
        ]);
    }

    public function test_holdings_sell_uses_lot_id_and_requires_48_hours(): void
    {
        MetalRate::query()->create([
            'metal_type' => 'gold',
            'rate_per_gram' => 7000,
            'currency' => 'INR',
        ]);

        $user = User::factory()->create([
            'phone' => '9876501003',
            'mpin' => '1234',
            'gold_holdings' => 50,
            'name' => 'Seller User',
        ]);
        Sanctum::actingAs($user);

        \App\Models\KycDetail::query()->create([
            'user_id' => $user->id,
            'full_name' => 'Seller User',
            'bank_name' => 'HDFC Bank',
            'account_holder_name' => 'Seller User',
            'account_number' => '12345678904521',
            'ifsc_code' => 'HDFC0001234',
        ]);

        // Fresh purchase — locked for 48h.
        $lot = Investment::query()->create([
            'user_id' => $user->id,
            'metal_type' => 'gold',
            'type' => 'buy',
            'quantity_grams' => 50,
            'remaining_grams' => 50,
            'rate_per_gram' => 7000,
            'amount' => 350000,
            'gst_amount' => 0,
            'total_amount' => 350000,
            'status' => 'completed',
            'hold_started_at' => now()->subHours(10),
            'purpose' => 'hold',
        ]);

        $locked = $this->postJson('/api/v1/holdings/sell', [
            'lot_id' => $lot->id,
        ]);

        $locked->assertStatus(422);
        $payload = json_encode($locked->json());
        $this->assertTrue(
            data_get($locked->json(), 'data.errors.lot_id') !== null
                || data_get($locked->json(), 'errors.lot_id') !== null
                || str_contains($payload, '48'),
            'Expected 48h sell lock validation. Got: '.$payload
        );

        // Mature lot.
        $lot->update(['hold_started_at' => now()->subHours(49)]);

        $this->postJson('/api/v1/holdings/sell', [
            'lot_id' => $lot->id,
        ])->assertCreated()
            ->assertJsonPath('data.auto_approve_hours', 2)
            ->assertJsonPath('data.sell_after_hours', 48)
            ->assertJsonPath('data.withdrawal.status', 'pending')
            ->assertJsonPath('data.withdrawal.quantity_grams', 50);

        $this->assertDatabaseHas('metal_withdrawals', [
            'user_id' => $user->id,
            'source_lot_id' => $lot->id,
            'quantity_grams' => 50,
            'status' => 'pending',
        ]);

        // Same lot cannot be sold again while request is pending.
        $duplicate = $this->postJson('/api/v1/holdings/sell', [
            'lot_id' => $lot->id,
        ]);

        $duplicate->assertStatus(422);
        $duplicatePayload = json_encode($duplicate->json());
        $this->assertTrue(
            data_get($duplicate->json(), 'data.errors.lot_id') !== null
                || data_get($duplicate->json(), 'errors.lot_id') !== null
                || str_contains(strtolower($duplicatePayload), 'already pending'),
            'Expected duplicate sell blocked. Got: '.$duplicatePayload
        );

        $list = $this->getJson('/api/v1/holdings?metal_type=gold')->assertOk();
        $lotRow = collect($list->json('data.lots'))->firstWhere('id', $lot->id);
        $this->assertNotNull($lotRow);
        $this->assertFalse((bool) ($lotRow['can_sell'] ?? true));
        $this->assertTrue((bool) ($lotRow['sell_request_pending'] ?? false));
    }
}
