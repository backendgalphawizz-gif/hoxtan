<?php

namespace Tests\Feature;

use App\Models\Investment;
use App\Models\JewelleryOrder;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountActivityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_and_view_orders(): void
    {
        $user = User::factory()->create(['phone' => '9876543210', 'mpin' => '1234']);
        Sanctum::actingAs($user);

        $order = JewelleryOrder::query()->create([
            'order_number' => 'HOX12345',
            'user_id' => $user->id,
            'subtotal' => 10000,
            'metal_value' => 8000,
            'making_charge_amount' => 1500,
            'gst_percent' => 3,
            'gst_amount' => 300,
            'discount_amount' => 0,
            'total_amount' => 10300,
            'status' => 'processing',
            'shipping_name' => 'Alexander Vance',
            'shipping_phone' => '9876543210',
            'shipping_address' => '12 MG Road, Mumbai',
            'delivery_otp' => '5678',
        ]);

        $product = \App\Models\JewelleryProduct::query()->create([
            'name' => 'Gold Ring',
            'metal_type' => 'gold',
            'purity' => '22K',
            'weight_grams' => 5,
            'price' => 10000,
            'stock_status' => 'in_stock',
            'is_active' => true,
        ]);

        \App\Models\JewelleryOrderItem::query()->create([
            'jewellery_order_id' => $order->id,
            'jewellery_product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 10000,
            'line_total' => 10000,
        ]);

        $this->getJson('/api/v1/orders/config')
            ->assertOk()
            ->assertJsonStructure(['data' => ['status_filters', 'statuses']]);

        $this->getJson('/api/v1/orders?status=processing')
            ->assertOk()
            ->assertJsonPath('data.orders.0.order_number', 'HOX12345')
            ->assertJsonPath('data.orders.0.status_label', 'Processing')
            ->assertJsonPath('data.orders.0.delivery_otp', '5678')
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonStructure([
                'data' => [
                    'orders' => [
                        [
                            'image_url',
                            'image_urls',
                            'items' => [
                                ['product' => ['image_url', 'image_urls']],
                            ],
                            'tracking',
                            'tracking_details' => [
                                'tracking_number',
                                'courier_name',
                                'dispatched_at',
                                'delivered_at',
                            ],
                        ],
                    ],
                ],
            ]);

        $this->getJson('/api/v1/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.order.order_number_display', '#HOX12345')
            ->assertJsonPath('data.order.delivery_otp', '5678')
            ->assertJsonPath('data.order.driver', null)
            ->assertJsonStructure([
                'data' => [
                    'order' => [
                        'items',
                        'payment',
                        'driver',
                        'tracking' => [
                            ['key', 'label', 'completed', 'current', 'completed_at'],
                        ],
                        'tracking_details' => [
                            'tracking_number',
                            'courier_name',
                            'dispatched_at',
                            'delivered_at',
                            'expected_delivery_date',
                            'expected_delivery_display',
                        ],
                    ],
                ],
            ])
            ->assertJsonPath('data.order.tracking.0.key', 'placed')
            ->assertJsonPath('data.order.tracking.1.current', true);

        $driver = \App\Models\Driver::query()->create([
            'name' => 'Ravi Kumar',
            'phone' => '9988776655',
            'vehicle_type' => 'bike',
            'vehicle_number' => 'MH12AB1234',
            'is_active' => true,
            'is_online' => true,
        ]);

        $order->update([
            'driver_id' => $driver->id,
            'driver_assigned_at' => now(),
        ]);

        $this->getJson('/api/v1/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.order.driver_id', $driver->id)
            ->assertJsonPath('data.order.driver.name', 'Ravi Kumar')
            ->assertJsonPath('data.order.driver.phone', '9988776655')
            ->assertJsonPath('data.order.driver.phone_display', '+91 9988776655')
            ->assertJsonPath('data.order.driver.vehicle_type', 'bike')
            ->assertJsonPath('data.order.driver.vehicle_number', 'MH12AB1234');
    }

    public function test_emi_order_track_payload_includes_timeline_progress_and_actions(): void
    {
        $user = User::factory()->create(['phone' => '9876543212', 'mpin' => '1234']);
        Sanctum::actingAs($user);

        \App\Models\KycDetail::query()->create([
            'user_id' => $user->id,
            'full_name' => 'Test User',
            'bank_name' => 'HDFC Bank',
            'account_holder_name' => 'Test User',
            'account_number' => '12345678904521',
            'ifsc_code' => 'HDFC0001234',
        ]);

        $product = \App\Models\JewelleryProduct::query()->create([
            'name' => 'Antique Gold Necklace',
            'metal_type' => 'gold',
            'purity' => '24K',
            'weight_grams' => 10,
            'price' => 90000,
            'stock_status' => 'in_stock',
            'is_active' => true,
        ]);

        $order = JewelleryOrder::query()->create([
            'order_number' => 'HGX78210',
            'user_id' => $user->id,
            'subtotal' => 90000,
            'metal_value' => 85000,
            'making_charge_amount' => 5000,
            'gst_percent' => 3,
            'gst_amount' => 2700,
            'discount_amount' => 0,
            'total_amount' => 92700,
            'payment_mode' => 'emi',
            'emi_tenure' => 12,
            'total_emi_cost' => 96235,
            'monthly_emi_amount' => 8019.58,
            'status' => 'pending',
            'shipping_name' => 'Alexander Vance',
            'shipping_phone' => '+91 98765 43210',
            'shipping_address' => "42B, Orchard Heights,\nResidency Road, Bengaluru 560025",
            'shipping_address_type' => 'home',
            'expected_delivery_date' => '2026-10-20',
        ]);

        \App\Models\JewelleryOrderItem::query()->create([
            'jewellery_order_id' => $order->id,
            'jewellery_product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 90000,
            'line_total' => 90000,
        ]);

        foreach (range(1, 12) as $number) {
            \App\Models\JewelleryOrderEmiInstallment::query()->create([
                'jewellery_order_id' => $order->id,
                'installment_number' => $number,
                'amount' => 8019.58,
                'due_date' => now()->startOfMonth()->addMonths($number - 1)->toDateString(),
                'status' => $number <= 4 ? 'paid' : 'pending',
                'paid_at' => $number <= 4 ? now()->subMonths(4 - $number) : null,
            ]);
        }

        $this->getJson('/api/v1/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.order.is_emi', true)
            ->assertJsonPath('data.order.product_specification', '24K | 10.0 gm')
            ->assertJsonPath('data.order.tracking.0.key', 'placed')
            ->assertJsonPath('data.order.tracking.1.key', 'reserved')
            ->assertJsonPath('data.order.tracking.2.key', 'emi_waiting')
            ->assertJsonPath('data.order.tracking.2.label', 'Waiting for EMI Completion')
            ->assertJsonPath('data.order.tracking.2.current', true)
            ->assertJsonPath('data.order.tracking.3.key', 'ready_for_delivery')
            ->assertJsonPath('data.order.tracking.4.key', 'delivered')
            ->assertJsonPath('data.order.emi.progress.paid_count', 4)
            ->assertJsonPath('data.order.emi.progress.total_count', 12)
            ->assertJsonPath('data.order.emi.progress.progress_label', '4/12 Installments Paid')
            ->assertJsonPath('data.order.emi.actions.0.key', 'cancel_emi_plan')
            ->assertJsonPath('data.order.emi.actions.1.key', 'withdraw_emi_value')
            ->assertJsonPath('data.order.emi.auto_debit_account.bank_name', 'HDFC Bank')
            ->assertJsonPath('data.order.delivery_address.name', 'Alexander Vance')
            ->assertJsonStructure([
                'data' => [
                    'order' => [
                        'emi' => [
                            'progress' => [
                                'paid_emi_display',
                                'remaining_display',
                                'last_emi_paid_display',
                                'next_auto_debit_display',
                            ],
                            'cancel_popup' => ['message', 'confirm_label'],
                            'withdrawal' => [
                                'order_value_display',
                                'deduction_amount_display',
                                'you_will_receive_display',
                                'credit_note',
                            ],
                        ],
                    ],
                ],
            ]);

        // Mark all paid → EMI Completed is current; Ready for Delivery waits for deliver API.
        \App\Models\JewelleryOrderEmiInstallment::query()
            ->where('jewellery_order_id', $order->id)
            ->where('status', 'pending')
            ->update(['status' => 'paid', 'paid_at' => now()]);

        $this->getJson('/api/v1/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.order.tracking.2.label', 'EMI Completed')
            ->assertJsonPath('data.order.tracking.2.current', true)
            ->assertJsonPath('data.order.tracking.3.key', 'ready_for_delivery')
            ->assertJsonPath('data.order.tracking.3.current', false)
            ->assertJsonPath('data.order.tracking.3.completed', false)
            ->assertJsonPath('data.order.emi.is_completed', true)
            ->assertJsonPath('data.order.emi.can_deliver', true)
            ->assertJsonPath('data.order.emi.actions.0.key', 'deliver_jewellery')
            ->assertJsonPath('data.order.emi.actions.0.endpoint', '/api/v1/orders/'.$order->id.'/emi/deliver')
            ->assertJsonPath('data.order.emi.actions.1.key', 'withdraw_emi_value');

        $this->postJson('/api/v1/orders/'.$order->id.'/emi/deliver')
            ->assertOk()
            ->assertJsonPath('data.order.tracking.3.key', 'ready_for_delivery')
            ->assertJsonPath('data.order.tracking.3.current', true)
            ->assertJsonPath('data.order.tracking.3.completed', true)
            ->assertJsonPath('data.order.emi.can_deliver', false)
            ->assertJsonPath('data.order.emi.delivery_requested', true);
    }

    public function test_user_can_list_and_view_transactions(): void
    {
        $user = User::factory()->create(['phone' => '9876543211', 'mpin' => '1234']);
        Sanctum::actingAs($user);

        Investment::query()->create([
            'user_id' => $user->id,
            'metal_type' => 'gold',
            'type' => 'buy',
            'quantity_grams' => 0.5,
            'rate_per_gram' => 7000,
            'amount' => 3500,
            'gst_amount' => 105,
            'total_amount' => 3605,
            'status' => 'completed',
        ]);

        WalletTransaction::query()->create([
            'user_id' => $user->id,
            'type' => 'credit',
            'amount' => 500,
            'balance_after' => 500,
            'description' => 'Admin credit',
            'source' => 'admin',
        ]);

        $this->getJson('/api/v1/transactions/config')
            ->assertOk()
            ->assertJsonStructure(['data' => ['filters', 'wallet_sources']]);

        $list = $this->getJson('/api/v1/transactions?filter=all');

        $list->assertOk()
            ->assertJsonPath('pagination.total', 2)
            ->assertJsonStructure([
                'data' => [
                    ['id', 'title', 'amount_display', 'status_label', 'occurred_at_display'],
                ],
            ]);

        $buy = collect($list->json('data'))->firstWhere('source_type', 'investment');
        $this->assertNotNull($buy);
        $this->assertNotNull($buy['certificate'] ?? null);
        $this->assertNotEmpty($buy['certificate']['certificate_number'] ?? null);
        $this->assertSame('24K', $buy['certificate']['purity'] ?? null);
        $this->assertSame(0.5, $buy['certificate']['holding_grams'] ?? null);
        $this->assertStringContainsString('/certificates/', $buy['certificate']['download_url'] ?? '');

        $transactionId = (string) ($buy['id'] ?? $list->json('data.0.id'));

        $this->getJson('/api/v1/transactions/'.$transactionId)
            ->assertOk()
            ->assertJsonPath('data.id', $transactionId)
            ->assertJsonPath('data.certificate.purity', '24K');
    }
}
