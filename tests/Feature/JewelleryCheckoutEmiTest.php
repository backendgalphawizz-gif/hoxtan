<?php

namespace Tests\Feature;

use App\Models\JewelleryEmiPlan;
use App\Models\JewelleryProduct;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JewelleryCheckoutEmiTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_summary_includes_emi_options(): void
    {
        $user = User::factory()->create(['phone' => '9876543220', 'mpin' => '1234']);
        Sanctum::actingAs($user);

        $address = UserAddress::query()->create([
            'user_id' => $user->id,
            'address_type' => 'home',
            'is_default' => true,
            'full_name' => 'Test User',
            'address_line' => 'MG Road',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'pincode' => '400001',
            'phone' => '9876543220',
        ]);

        $product = JewelleryProduct::query()->create([
            'name' => 'Gold Ring',
            'metal_type' => 'gold',
            'purity' => '22K',
            'weight_grams' => 10,
            'price' => 100000,
            'stock_status' => 'in_stock',
            'is_active' => true,
        ]);

        $plan = JewelleryEmiPlan::query()->create([
            'tenure_months' => 6,
            'interest_rate_percent' => 12,
            'label' => '6 months EMI',
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/jewellery/checkout/summary?'.http_build_query([
            'product_id' => $product->id,
            'quantity' => 1,
            'address_id' => $address->id,
            'emi_plan_id' => $plan->id,
        ]))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'payment_modes',
                    'emi' => [
                        'options' => [
                            ['id', 'tenure_months', 'total_emi_cost', 'monthly_emi_amount'],
                        ],
                        'selected',
                    ],
                ],
            ])
            ->assertJsonPath('data.emi.selected.id', $plan->id)
            ->assertJsonPath('data.emi.selected.tenure_months', 6);
    }

    public function test_buy_now_with_emi_stores_tenure_and_total_emi_cost(): void
    {
        $user = User::factory()->create(['phone' => '9876543221', 'mpin' => '1234']);
        Sanctum::actingAs($user);

        UserAddress::query()->create([
            'user_id' => $user->id,
            'address_type' => 'home',
            'is_default' => true,
            'full_name' => 'Test User',
            'address_line' => 'MG Road',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'pincode' => '400001',
            'phone' => '9876543221',
        ]);

        $product = JewelleryProduct::query()->create([
            'name' => 'Gold Bangle',
            'metal_type' => 'gold',
            'purity' => '22K',
            'weight_grams' => 10,
            'price' => 120000,
            'stock_status' => 'in_stock',
            'is_active' => true,
        ]);

        $plan = JewelleryEmiPlan::query()->create([
            'tenure_months' => 6,
            'interest_rate_percent' => 12,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/jewellery/checkout/buy-now', [
            'product_id' => $product->id,
            'quantity' => 1,
            'payment_mode' => 'emi',
            'emi_plan_id' => $plan->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.order.payment_mode', 'emi')
            ->assertJsonPath('data.order.emi.tenure_months', 6);

        $totalAmount = (float) $response->json('data.order.total_amount');
        $expected = $plan->calculateForAmount($totalAmount);

        $response
            ->assertJsonPath('data.order.emi.total_emi_cost', $expected['total_emi_cost'])
            ->assertJsonPath('data.order.emi.monthly_emi_amount', $expected['monthly_emi_amount']);

        $this->assertDatabaseHas('jewellery_orders', [
            'user_id' => $user->id,
            'payment_mode' => 'emi',
            'jewellery_emi_plan_id' => $plan->id,
            'emi_tenure' => 6,
            'total_emi_cost' => $expected['total_emi_cost'],
            'monthly_emi_amount' => $expected['monthly_emi_amount'],
        ]);
    }

    public function test_emi_plan_id_is_required_when_payment_mode_is_emi(): void
    {
        $user = User::factory()->create(['phone' => '9876543222', 'mpin' => '1234']);
        Sanctum::actingAs($user);

        UserAddress::query()->create([
            'user_id' => $user->id,
            'address_type' => 'home',
            'is_default' => true,
            'full_name' => 'Test User',
            'address_line' => 'MG Road',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'pincode' => '400001',
            'phone' => '9876543222',
        ]);

        $product = JewelleryProduct::query()->create([
            'name' => 'Gold Chain',
            'metal_type' => 'gold',
            'purity' => '22K',
            'weight_grams' => 8,
            'price' => 80000,
            'stock_status' => 'in_stock',
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/jewellery/checkout/buy-now', [
            'product_id' => $product->id,
            'payment_mode' => 'emi',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['emi_plan_id']);
    }
}
