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
        ]);

        $this->getJson('/api/v1/orders/config')
            ->assertOk()
            ->assertJsonStructure(['data' => ['status_filters', 'statuses']]);

        $this->getJson('/api/v1/orders?status=processing')
            ->assertOk()
            ->assertJsonPath('data.orders.0.order_number', 'HOX12345')
            ->assertJsonPath('data.orders.0.status_label', 'Processing')
            ->assertJsonPath('data.pagination.total', 1);

        $this->getJson('/api/v1/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.order.order_number_display', '#HOX12345')
            ->assertJsonStructure(['data' => ['order' => ['items', 'payment']]]);
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
            'description' => 'Welcome bonus',
            'source' => 'welcome_bonus',
        ]);

        $this->getJson('/api/v1/transactions/config')
            ->assertOk()
            ->assertJsonStructure(['data' => ['filters', 'wallet_sources']]);

        $list = $this->getJson('/api/v1/transactions?filter=all');

        $list->assertOk()
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonStructure([
                'data' => [
                    'transactions' => [
                        ['id', 'title', 'amount_display', 'status_label', 'occurred_at_display'],
                    ],
                ],
            ]);

        $transactionId = $list->json('data.transactions.0.id');

        $this->getJson('/api/v1/transactions/'.$transactionId)
            ->assertOk()
            ->assertJsonPath('data.transaction.id', $transactionId);
    }
}
