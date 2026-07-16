<?php

namespace Tests\Feature;

use App\Models\JewelleryEmiPlan;
use App\Models\JewelleryOrder;
use App\Models\JewelleryOrderEmiInstallment;
use App\Models\JewelleryProduct;
use App\Models\UserAddress;
use App\Services\RazorpayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JewelleryEmiPayAllApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_pay_all_preview_and_force_pays_remaining_emis_via_razorpay(): void
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
        $this->assertSame(3, $order->emiInstallments()->where('status', 'pending')->count());

        // Mark first EMI paid manually so pay-all covers remaining only.
        $first = $order->emiInstallments()->orderBy('installment_number')->first();
        $first->update([
            'status' => 'paid',
            'paid_at' => now(),
            'notes' => 'Already paid',
        ]);

        $remaining = round((float) $order->emiInstallments()->where('status', 'pending')->sum('amount'), 2);
        $remainingPaise = (int) round($remaining * 100);

        $preview = $this->getJson('/api/v1/orders/'.$orderId.'/emi/pay-all-preview')
            ->assertOk()
            ->assertJsonPath('data.pay_all.pending_count', 2)
            ->assertJsonPath('data.pay_all.can_pay', true)
            ->assertJsonPath('data.pay_all.default_payment_method', 'razorpay');

        $this->assertEquals($remaining, (float) $preview->json('data.pay_all.amount'));
        $this->assertContains('razorpay', $preview->json('data.pay_all.payment_methods'));

        $this->mock(RazorpayService::class, function ($mock) use ($remainingPaise): void {
            $mock->shouldReceive('createOrder')
                ->once()
                ->andReturn([
                    'id' => 'order_test_emi_all',
                    'amount' => $remainingPaise,
                    'currency' => 'INR',
                    'receipt' => 'EMIALL-1',
                    'status' => 'created',
                ]);
            $mock->shouldReceive('keyId')->andReturn('rzp_test_key');
            $mock->shouldReceive('verifyPaymentSignature')->once();
            $mock->shouldReceive('fetchPayment')
                ->once()
                ->with('pay_test_emi_all')
                ->andReturn([
                    'id' => 'pay_test_emi_all',
                    'status' => 'captured',
                    'amount' => $remainingPaise,
                    'order_id' => 'order_test_emi_all',
                    'method' => 'upi',
                ]);
        });

        $pay = $this->postJson('/api/v1/orders/'.$orderId.'/emi/pay-all')
            ->assertOk()
            ->assertJsonPath('data.payment_method', 'razorpay')
            ->assertJsonPath('data.requires_verification', true)
            ->assertJsonPath('data.installments_paid', 0)
            ->assertJsonPath('data.fully_paid', false)
            ->assertJsonPath('data.razorpay.order_id', 'order_test_emi_all')
            ->assertJsonPath('data.razorpay.key', 'rzp_test_key');

        $this->assertEquals($remaining, (float) $pay->json('data.amount_paid'));
        $this->assertSame(2, JewelleryOrderEmiInstallment::query()
            ->where('jewellery_order_id', $orderId)
            ->where('status', 'pending')
            ->count());

        $this->assertDatabaseHas('payments', [
            'payable_type' => JewelleryOrder::class,
            'payable_id' => $orderId,
            'gateway' => 'razorpay',
            'gateway_reference' => 'order_test_emi_all',
            'status' => 'pending',
        ]);

        $verify = $this->postJson('/api/v1/orders/'.$orderId.'/emi/pay-all/verify', [
            'razorpay_order_id' => 'order_test_emi_all',
            'razorpay_payment_id' => 'pay_test_emi_all',
            'razorpay_signature' => 'sig_test',
        ])
            ->assertOk()
            ->assertJsonPath('data.installments_paid', 2)
            ->assertJsonPath('data.fully_paid', true)
            ->assertJsonPath('data.delivery_unlocked', true)
            ->assertJsonPath('data.payment_method', 'razorpay')
            ->assertJsonPath('data.order.emi.remaining_amount', 0);

        $this->assertEquals($remaining, (float) $verify->json('data.amount_paid'));
        $this->assertSame(0, JewelleryOrderEmiInstallment::query()
            ->where('jewellery_order_id', $orderId)
            ->where('status', 'pending')
            ->count());

        $this->assertDatabaseHas('payments', [
            'gateway_reference' => 'order_test_emi_all',
            'gateway_payment_id' => 'pay_test_emi_all',
            'status' => 'completed',
        ]);

        // Wallet untouched for Razorpay path.
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
            ->assertJsonPath('data.requires_verification', false)
            ->assertJsonPath('data.fully_paid', true);

        $user->refresh();
        $this->assertEqualsWithDelta(500000 - $remaining, (float) $user->wallet_balance, 0.01);
    }

    public function test_pay_all_wallet_rejects_insufficient_balance(): void
    {
        $user = $this->userWithTransactionKyc([
            'phone' => '9876507779',
            'mpin' => '1234',
            'wallet_balance' => 10,
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
            'phone' => '9876507779',
        ]);

        $product = JewelleryProduct::query()->create([
            'name' => 'Gold Bangle',
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

        $this->postJson('/api/v1/orders/'.$orderId.'/emi/pay-all', [
            'payment_method' => 'wallet',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame(3, JewelleryOrderEmiInstallment::query()
            ->where('jewellery_order_id', $orderId)
            ->where('status', 'pending')
            ->count());
    }
}
