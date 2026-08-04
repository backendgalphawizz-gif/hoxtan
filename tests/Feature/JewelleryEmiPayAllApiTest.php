<?php

namespace Tests\Feature;

use App\Models\JewelleryEmiPlan;
use App\Models\JewelleryOrder;
use App\Models\JewelleryOrderEmiInstallment;
use App\Models\JewelleryProduct;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JewelleryEmiPayAllApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_pay_all_marks_remaining_emis_paid_directly(): void
    {
        $user = $this->userWithTransactionKyc([
            'phone' => '9876507777',
            'mpin' => '1234',
            'wallet_balance' => 100,
        ]);
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
            'phone' => '9876507777',
        ]);

        $product = JewelleryProduct::query()->create([
            'name' => 'Gold Ring',
            'metal_type' => 'gold',
            'purity' => '22K',
            'weight_grams' => 5,
            'price' => 60000,
            'stock_status' => 'in_stock',
            'is_active' => true,
        ]);

        $plan = JewelleryEmiPlan::query()->create([
            'tenure_months' => 3,
            'interest_rate_percent' => 0,
            'is_active' => true,
        ]);

        $buy = $this->postJson('/api/v1/jewellery/checkout/buy-now', [
            'product_id' => $product->id,
            'quantity' => 1,
            'payment_type' => 'emi',
            'emi_plan_id' => $plan->id,
        ])->assertCreated();

        $orderId = (int) $buy->json('data.order.id');
        $order = JewelleryOrder::query()->findOrFail($orderId);

        $this->assertSame(3, $order->emiInstallments()->count());

        $first = $order->emiInstallments()->orderBy('installment_number')->first();
        $first->update([
            'status' => 'paid',
            'paid_at' => now(),
            'notes' => 'Already paid',
        ]);

        $remaining = round((float) $order->emiInstallments()->where('status', 'pending')->sum('amount'), 2);

        $this->getJson('/api/v1/orders/'.$orderId.'/emi/pay-all-preview')
            ->assertOk()
            ->assertJsonPath('data.pay_all.pending_count', 2)
            ->assertJsonPath('data.pay_all.can_pay', true)
            ->assertJsonPath('data.pay_all.default_payment_method', 'direct');

        $pay = $this->postJson('/api/v1/orders/'.$orderId.'/emi/pay-all')
            ->assertOk()
            ->assertJsonPath('data.payment_method', 'direct')
            ->assertJsonPath('data.installments_paid', 2)
            ->assertJsonPath('data.fully_paid', true)
            ->assertJsonPath('data.delivery_unlocked', true)
            ->assertJsonPath('data.order.emi.remaining_amount', 0);

        $this->assertEquals($remaining, (float) $pay->json('data.amount_paid'));
        $this->assertSame(0, JewelleryOrderEmiInstallment::query()
            ->where('jewellery_order_id', $orderId)
            ->where('status', 'pending')
            ->count());

        // Direct pay does not touch wallet.
        $user->refresh();
        $this->assertEqualsWithDelta(100.0, (float) $user->wallet_balance, 0.01);
    }

    public function test_pay_all_via_wallet_still_works(): void
    {
        $user = $this->userWithTransactionKyc([
            'phone' => '9876507778',
            'mpin' => '1234',
            'wallet_balance' => 500000,
        ]);
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
            'phone' => '9876507778',
        ]);

        $product = JewelleryProduct::query()->create([
            'name' => 'Gold Chain',
            'metal_type' => 'gold',
            'purity' => '22K',
            'weight_grams' => 8,
            'price' => 90000,
            'stock_status' => 'in_stock',
            'is_active' => true,
        ]);

        $plan = JewelleryEmiPlan::query()->create([
            'tenure_months' => 3,
            'interest_rate_percent' => 0,
            'is_active' => true,
        ]);

        $orderId = (int) $this->postJson('/api/v1/jewellery/checkout/buy-now', [
            'product_id' => $product->id,
            'quantity' => 1,
            'payment_type' => 'emi',
            'emi_plan_id' => $plan->id,
        ])->assertCreated()->json('data.order.id');

        $remaining = round((float) JewelleryOrderEmiInstallment::query()
            ->where('jewellery_order_id', $orderId)
            ->where('status', 'pending')
            ->sum('amount'), 2);

        $this->postJson('/api/v1/orders/'.$orderId.'/emi/pay-all', [
            'payment_method' => 'wallet',
        ])
            ->assertOk()
            ->assertJsonPath('data.payment_method', 'wallet')
            ->assertJsonPath('data.fully_paid', true);

        $user->refresh();
        $this->assertEqualsWithDelta(500000 - $remaining, (float) $user->wallet_balance, 0.01);
    }
}
