<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\JewelleryEmiPlan;
use App\Models\JewelleryOrder;
use App\Models\JewelleryOrderEmiInstallment;
use App\Models\JewelleryProduct;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\JewelleryEmiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JewelleryInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_payment_buy_now_generates_invoice_and_lists_in_api(): void
    {
        $user = $this->userWithTransactionKyc(['phone' => '9876500001', 'mpin' => '1234']);
        Sanctum::actingAs($user);

        $address = UserAddress::query()->create([
            'user_id' => $user->id,
            'address_type' => 'home',
            'is_default' => true,
            'full_name' => 'Invoice User',
            'address_line' => 'MG Road',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'pincode' => '400001',
            'phone' => '9876500001',
        ]);

        $product = JewelleryProduct::query()->create([
            'name' => 'Gold Ring Invoice',
            'metal_type' => 'gold',
            'purity' => '22K',
            'weight_grams' => 10,
            'price' => 100000,
            'stock_status' => 'in_stock',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/jewellery/checkout/buy-now', [
            'product_id' => $product->id,
            'quantity' => 1,
            'address_id' => $address->id,
            'payment_type' => 'full',
            'payment_method' => 'razorpay',
            'transaction_id' => 'TXN-JEWEL-001',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.payment.status', 'completed')
            ->assertJsonPath('data.invoice.source_type', 'jewellery')
            ->assertJsonPath('data.invoice.order_number', $response->json('data.order.order_number'));

        $orderId = (int) $response->json('data.order.id');
        $invoiceNumber = $response->json('data.invoice.invoice_number');

        $this->assertDatabaseHas('payments', [
            'reference_id' => 'TXN-JEWEL-001',
            'status' => 'completed',
            'gateway' => 'razorpay',
        ]);

        $this->assertDatabaseHas('invoices', [
            'jewellery_order_id' => $orderId,
            'invoice_number' => $invoiceNumber,
            'user_id' => $user->id,
        ]);

        $this->getJson('/api/v1/invoices')
            ->assertOk()
            ->assertJsonPath('data.invoices.0.invoice_number', $invoiceNumber)
            ->assertJsonPath('data.invoices.0.source_type', 'jewellery')
            ->assertJsonPath('data.invoices.0.order_number', $response->json('data.order.order_number'));

        $this->getJson('/api/v1/transactions?filter=jewellery')
            ->assertOk()
            ->assertJsonFragment([
                'invoice_number' => $invoiceNumber,
            ]);

        $this->getJson('/api/v1/orders/'.$orderId)
            ->assertOk()
            ->assertJsonPath('data.order.invoice.invoice_number', $invoiceNumber);
    }

    public function test_emi_buy_now_does_not_generate_invoice_until_fully_paid(): void
    {
        $user = $this->userWithTransactionKyc(['phone' => '9876500002', 'mpin' => '1234']);
        Sanctum::actingAs($user);

        $address = UserAddress::query()->create([
            'user_id' => $user->id,
            'address_type' => 'home',
            'is_default' => true,
            'full_name' => 'EMI User',
            'address_line' => 'MG Road',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'pincode' => '400001',
            'phone' => '9876500002',
        ]);

        $product = JewelleryProduct::query()->create([
            'name' => 'Gold Chain EMI',
            'metal_type' => 'gold',
            'purity' => '22K',
            'weight_grams' => 8,
            'price' => 80000,
            'stock_status' => 'in_stock',
            'is_active' => true,
        ]);

        $plan = JewelleryEmiPlan::query()->create([
            'tenure_months' => 2,
            'interest_rate_percent' => 0,
            'label' => '2 months',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/jewellery/checkout/buy-now', [
            'product_id' => $product->id,
            'quantity' => 1,
            'address_id' => $address->id,
            'payment_type' => 'emi',
            'emi_plan_id' => $plan->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.payment.status', 'pending')
            ->assertJsonPath('data.invoice', null);

        $order = JewelleryOrder::query()->findOrFail($response->json('data.order.id'));
        $this->assertDatabaseMissing('invoices', ['jewellery_order_id' => $order->id]);

        $emi = app(JewelleryEmiService::class);
        $installments = JewelleryOrderEmiInstallment::query()
            ->where('jewellery_order_id', $order->id)
            ->orderBy('installment_number')
            ->get();

        $this->assertCount(2, $installments);

        $emi->markInstallmentPaid($installments[0]);
        $this->assertDatabaseMissing('invoices', ['jewellery_order_id' => $order->id]);

        $emi->markInstallmentPaid($installments[1]);

        $this->assertDatabaseHas('invoices', [
            'jewellery_order_id' => $order->id,
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $order->payment_id,
            'status' => 'completed',
        ]);
    }
}
