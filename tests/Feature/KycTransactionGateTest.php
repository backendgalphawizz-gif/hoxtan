<?php

namespace Tests\Feature;

use App\Models\MetalRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KycTransactionGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_buy_metal_purchase_blocked_without_completed_kyc(): void
    {
        MetalRate::query()->create([
            'metal_type' => 'gold',
            'rate_per_gram' => 7000,
            'currency' => 'INR',
        ]);

        $user = User::factory()->create([
            'wallet_balance' => 10000,
            'kyc_status' => 'pending',
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/buy-metal/purchase', [
            'metal_type' => 'gold',
            'input_mode' => 'currency',
            'amount' => 1000,
            'payment_method' => 'wallet',
        ])
            ->assertStatus(422)
            ->assertJsonPath('data.errors.kyc.0', 'Complete KYC verification (PAN, Aadhaar, and bank account) before proceeding.');
    }

    public function test_buy_metal_purchase_allowed_with_approved_kyc(): void
    {
        MetalRate::query()->create([
            'metal_type' => 'gold',
            'rate_per_gram' => 7000,
            'currency' => 'INR',
        ]);

        $user = $this->userWithTransactionKyc([
            'wallet_balance' => 10000,
            'gold_holdings' => 0,
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/buy-metal/purchase', [
            'metal_type' => 'gold',
            'input_mode' => 'currency',
            'amount' => 1000,
            'payment_method' => 'wallet',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true);
    }
}
