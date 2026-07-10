<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\JewelleryOrder;
use App\Models\JewelleryOrderItem;
use App\Models\JewelleryProduct;
use App\Models\OldGoldBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverDeliveriesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_deliveries_returns_tabs_tasks_and_filters(): void
    {
        $token = $this->driverAuthToken(['phone' => '9876543600']);
        $driver = Driver::query()->where('phone', '9876543600')->firstOrFail();
        $user = User::factory()->create(['name' => 'Alexander Vance']);

        $product = JewelleryProduct::query()->create([
            'name' => 'Antique Gold Necklace',
            'metal_type' => 'gold',
            'purity' => '22K',
            'weight_grams' => 26,
            'price' => 96235,
            'stock_status' => 'in_stock',
            'is_active' => true,
        ]);

        $acceptedOrder = JewelleryOrder::query()->create([
            'order_number' => 'PR-79451236',
            'user_id' => $user->id,
            'driver_id' => $driver->id,
            'subtotal' => 96235,
            'total_amount' => 96235,
            'status' => 'processing',
            'shipping_name' => 'Alexander Vance',
            'shipping_address' => '5th 134, Greenfield Street, Ave, Manchester',
        ]);

        JewelleryOrderItem::query()->create([
            'jewellery_order_id' => $acceptedOrder->id,
            'jewellery_product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 96235,
            'line_total' => 96235,
        ]);

        $pickedUpOrder = JewelleryOrder::query()->create([
            'order_number' => 'PR-79451237',
            'user_id' => $user->id,
            'driver_id' => $driver->id,
            'subtotal' => 50000,
            'total_amount' => 50000,
            'status' => 'processing',
            'picked_up_at' => now(),
            'shipping_address' => 'Another address',
        ]);

        OldGoldBooking::query()->create([
            'booking_number' => 'SELL73218',
            'user_id' => $user->id,
            'driver_id' => $driver->id,
            'metal_type' => 'gold',
            'purity' => '22K',
            'estimated_weight_grams' => 25,
            'quoted_amount' => 140628,
            'status' => 'pickup_scheduling',
            'pickup_name' => 'Alexander Vance',
            'pickup_address' => '14 Kensington Gardens, London',
        ]);

        $response = $this->getJson('/api/v1/driver/deliveries', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.search_placeholder', 'Search for orders ID, Sell ID...')
            ->assertJsonPath('data.tabs.0.value', 'all')
            ->assertJsonPath('data.tabs.0.count', 3)
            ->assertJsonPath('data.tabs.1.value', 'order')
            ->assertJsonPath('data.tabs.1.count', 2)
            ->assertJsonPath('data.tabs.2.value', 'pickup')
            ->assertJsonPath('data.tabs.2.count', 1)
            ->assertJsonCount(3, 'data.tasks')
            ->assertJsonStructure([
                'data' => [
                    'tabs' => [['value', 'label', 'count']],
                    'filters' => [['value', 'label']],
                    'tasks' => [[
                        'display_id',
                        'display_id_label',
                        'scheduled_at_display',
                        'status_tag' => ['key', 'label', 'color'],
                        'product_name',
                        'product_details' => ['display'],
                        'amount_display',
                        'customer' => ['name'],
                        'location' => ['type', 'address'],
                        'detail_path',
                    ]],
                    'pagination',
                ],
            ]);

        $tasks = collect($response->json('data.tasks'));
        $deliveryTask = $tasks->firstWhere('reference_id', 'PR-79451236');
        $pickupTask = $tasks->firstWhere('task_type', 'pickup');

        $this->assertSame('Order ID', $deliveryTask['display_id_label']);
        $this->assertSame('Accepted', $deliveryTask['status_tag']['label']);
        $this->assertSame('Antique Gold Necklace', $deliveryTask['product_name']);
        $this->assertStringContainsString('Estimated Weight:', $deliveryTask['product_details']['display']);

        $this->assertSame('Sell ID', $pickupTask['display_id_label']);
        $this->assertSame('Jewellery Pickup', $pickupTask['status_tag']['label']);
        $this->assertSame('#SELL73218', $pickupTask['display_id']);
    }

    public function test_driver_deliveries_can_filter_by_type(): void
    {
        $token = $this->driverAuthToken(['phone' => '9876543601']);
        $driver = Driver::query()->where('phone', '9876543601')->firstOrFail();
        $user = User::factory()->create();

        JewelleryOrder::query()->create([
            'order_number' => 'PR-80001',
            'user_id' => $user->id,
            'driver_id' => $driver->id,
            'subtotal' => 1000,
            'total_amount' => 1000,
            'status' => 'processing',
        ]);

        OldGoldBooking::query()->create([
            'booking_number' => 'SELL80001',
            'user_id' => $user->id,
            'driver_id' => $driver->id,
            'metal_type' => 'gold',
            'purity' => '22K',
            'estimated_weight_grams' => 10,
            'quoted_amount' => 50000,
            'status' => 'pickup_scheduling',
        ]);

        $this->getJson('/api/v1/driver/deliveries?type=order', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonCount(1, 'data.tasks')
            ->assertJsonPath('data.tasks.0.task_type', 'delivery');

        $this->getJson('/api/v1/driver/deliveries?type=pickup', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonCount(1, 'data.tasks')
            ->assertJsonPath('data.tasks.0.task_type', 'pickup');
    }

    public function test_driver_deliveries_can_filter_by_delivery_status(): void
    {
        $token = $this->driverAuthToken(['phone' => '9876543602']);
        $driver = Driver::query()->where('phone', '9876543602')->firstOrFail();
        $user = User::factory()->create();

        JewelleryOrder::query()->create([
            'order_number' => 'PR-81001',
            'user_id' => $user->id,
            'driver_id' => $driver->id,
            'subtotal' => 1000,
            'total_amount' => 1000,
            'status' => 'processing',
        ]);

        JewelleryOrder::query()->create([
            'order_number' => 'PR-81002',
            'user_id' => $user->id,
            'driver_id' => $driver->id,
            'subtotal' => 2000,
            'total_amount' => 2000,
            'status' => 'processing',
            'picked_up_at' => now(),
        ]);

        $this->getJson('/api/v1/driver/deliveries?type=order&status=picked_up', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonCount(1, 'data.tasks')
            ->assertJsonPath('data.tasks.0.status_tag.key', 'picked_up');
    }

    public function test_driver_deliveries_can_search_by_order_or_sell_id(): void
    {
        $token = $this->driverAuthToken(['phone' => '9876543603']);
        $driver = Driver::query()->where('phone', '9876543603')->firstOrFail();
        $user = User::factory()->create();

        JewelleryOrder::query()->create([
            'order_number' => 'PR-SEARCH01',
            'user_id' => $user->id,
            'driver_id' => $driver->id,
            'subtotal' => 1000,
            'total_amount' => 1000,
            'status' => 'processing',
        ]);

        OldGoldBooking::query()->create([
            'booking_number' => 'SELLSEARCH01',
            'user_id' => $user->id,
            'driver_id' => $driver->id,
            'metal_type' => 'gold',
            'purity' => '22K',
            'estimated_weight_grams' => 10,
            'quoted_amount' => 50000,
            'status' => 'pickup_scheduling',
        ]);

        $this->getJson('/api/v1/driver/deliveries?search=PR-SEARCH01', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonCount(1, 'data.tasks')
            ->assertJsonPath('data.tasks.0.reference_id', 'PR-SEARCH01');

        $this->getJson('/api/v1/driver/deliveries?search=SELLSEARCH01', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonCount(1, 'data.tasks')
            ->assertJsonPath('data.tasks.0.reference_id', 'SELLSEARCH01');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function driverAuthToken(array $attributes = []): string
    {
        config(['otp.expose_in_response' => true]);

        $driver = Driver::query()->create(array_merge([
            'name' => 'Test Driver',
            'phone' => '9876500000',
            'vehicle_type' => 'bike',
            'is_active' => true,
        ], $attributes));

        $send = $this->postJson('/api/v1/driver/login/send-otp', [
            'phone' => $driver->phone,
        ]);

        $verify = $this->postJson('/api/v1/driver/login/verify-otp', [
            'phone' => $driver->phone,
            'otp' => $send->json('data.otp'),
        ]);

        return (string) $verify->json('data.token');
    }
}
