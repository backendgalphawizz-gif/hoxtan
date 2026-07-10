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

class DriverHomeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_home_returns_profile_statistics_and_assigned_tasks(): void
    {
        $token = $this->driverAuthToken([
            'name' => 'Aman Kumar',
            'phone' => '9876543400',
            'is_online' => true,
        ]);

        $driver = Driver::query()->where('phone', '9876543400')->firstOrFail();
        $user = User::factory()->create(['name' => 'Alexander Vance']);

        $product = JewelleryProduct::query()->create([
            'name' => 'Antique Gold Necklace',
            'metal_type' => 'gold',
            'purity' => '22K',
            'weight_grams' => 12.5,
            'price' => 96235,
            'stock_status' => 'in_stock',
            'is_active' => true,
        ]);

        $delivery = JewelleryOrder::query()->create([
            'order_number' => 'PR-78451236',
            'user_id' => $user->id,
            'driver_id' => $driver->id,
            'subtotal' => 96235,
            'total_amount' => 96235,
            'status' => 'processing',
            'shipping_name' => 'Alexander Vance',
            'shipping_address' => '221B Baker Street, London',
        ]);

        JewelleryOrderItem::query()->create([
            'jewellery_order_id' => $delivery->id,
            'jewellery_product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 96235,
            'line_total' => 96235,
        ]);

        OldGoldBooking::query()->create([
            'booking_number' => 'SELL78210',
            'user_id' => $user->id,
            'driver_id' => $driver->id,
            'metal_type' => 'gold',
            'purity' => '22K',
            'estimated_weight_grams' => 25,
            'quoted_amount' => 160625,
            'status' => 'pickup_scheduling',
            'pickup_name' => 'Alexander Vance',
            'pickup_address' => '14 Kensington Gardens, London',
        ]);

        $response = $this->getJson('/api/v1/driver/home', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.driver.name', 'Aman Kumar')
            ->assertJsonPath('data.driver.availability_status', 'online')
            ->assertJsonPath('data.statistics.assigned_orders', 1)
            ->assertJsonPath('data.statistics.jewellery_pickups', 1)
            ->assertJsonPath('data.statistics.pending.assigned_orders', 1)
            ->assertJsonPath('data.statistics.pending.jewellery_pickups', 1)
            ->assertJsonPath('data.statistics.completed.assigned_orders', 0)
            ->assertJsonPath('data.statistics.completed.jewellery_pickups', 0)
            ->assertJsonCount(2, 'data.assigned_tasks');

        $tasks = collect($response->json('data.assigned_tasks'));

        $deliveryTask = $tasks->firstWhere('task_type', 'delivery');
        $pickupTask = $tasks->firstWhere('task_type', 'pickup');

        $this->assertNotNull($deliveryTask);
        $this->assertSame($delivery->id, $deliveryTask['order_id']);
        $this->assertNull($deliveryTask['pickup_id']);
        $this->assertSame('driver/tasks/deliveries/'.$delivery->id, $deliveryTask['detail_path']);
        $this->assertSame('delivery:'.$delivery->id, $deliveryTask['resource_key']);

        $this->assertNotNull($pickupTask);
        $this->assertNull($pickupTask['order_id']);
        $this->assertSame($pickupTask['pickup_id'], $pickupTask['id']);
        $this->assertSame('driver/tasks/pickups/'.$pickupTask['id'], $pickupTask['detail_path']);
        $this->assertSame('pickup:'.$pickupTask['id'], $pickupTask['resource_key']);
    }

    public function test_driver_statistics_endpoint_returns_counts(): void
    {
        $token = $this->driverAuthToken(['phone' => '9876543401']);
        $driver = Driver::query()->where('phone', '9876543401')->firstOrFail();
        $user = User::factory()->create();

        JewelleryOrder::query()->create([
            'order_number' => 'PR-10001',
            'user_id' => $user->id,
            'driver_id' => $driver->id,
            'subtotal' => 1000,
            'total_amount' => 1000,
            'status' => 'completed',
        ]);

        JewelleryOrder::query()->create([
            'order_number' => 'PR-10002',
            'user_id' => $user->id,
            'driver_id' => $driver->id,
            'subtotal' => 2000,
            'total_amount' => 2000,
            'status' => 'processing',
        ]);

        OldGoldBooking::query()->create([
            'booking_number' => 'SELL10001',
            'user_id' => $user->id,
            'driver_id' => $driver->id,
            'metal_type' => 'gold',
            'purity' => '24K',
            'estimated_weight_grams' => 10,
            'quoted_amount' => 50000,
            'status' => 'completed',
        ]);

        $this->getJson('/api/v1/driver/statistics', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('data.statistics.assigned_orders', 2)
            ->assertJsonPath('data.statistics.jewellery_pickups', 1)
            ->assertJsonPath('data.statistics.completed.assigned_orders', 1)
            ->assertJsonPath('data.statistics.pending.assigned_orders', 1)
            ->assertJsonPath('data.statistics.completed.jewellery_pickups', 1)
            ->assertJsonPath('data.statistics.pending.jewellery_pickups', 0);
    }

    public function test_driver_tasks_can_filter_by_type_and_status(): void
    {
        $token = $this->driverAuthToken(['phone' => '9876543402']);
        $driver = Driver::query()->where('phone', '9876543402')->firstOrFail();
        $user = User::factory()->create();

        JewelleryOrder::query()->create([
            'order_number' => 'PR-20001',
            'user_id' => $user->id,
            'driver_id' => $driver->id,
            'subtotal' => 1000,
            'total_amount' => 1000,
            'status' => 'processing',
        ]);

        OldGoldBooking::query()->create([
            'booking_number' => 'SELL20001',
            'user_id' => $user->id,
            'driver_id' => $driver->id,
            'metal_type' => 'gold',
            'purity' => '22K',
            'estimated_weight_grams' => 5,
            'quoted_amount' => 25000,
            'status' => 'completed',
        ]);

        $this->getJson('/api/v1/driver/tasks?type=delivery', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonCount(1, 'data.tasks')
            ->assertJsonPath('data.tasks.0.task_type', 'delivery');

        $this->getJson('/api/v1/driver/tasks?type=pickup&status=completed', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonCount(1, 'data.tasks')
            ->assertJsonPath('data.tasks.0.task_type', 'pickup')
            ->assertJsonPath('data.tasks.0.is_completed', true);
    }

    public function test_driver_can_view_assigned_delivery_and_pickup_details(): void
    {
        $token = $this->driverAuthToken(['phone' => '9876543403']);
        $driver = Driver::query()->where('phone', '9876543403')->firstOrFail();
        $user = User::factory()->create();

        $order = JewelleryOrder::query()->create([
            'order_number' => 'PR-30001',
            'user_id' => $user->id,
            'driver_id' => $driver->id,
            'subtotal' => 1000,
            'total_amount' => 1000,
            'status' => 'processing',
            'shipping_address' => 'Delivery address',
        ]);

        $booking = OldGoldBooking::query()->create([
            'booking_number' => 'SELL30001',
            'user_id' => $user->id,
            'driver_id' => $driver->id,
            'metal_type' => 'gold',
            'purity' => '22K',
            'estimated_weight_grams' => 8,
            'quoted_amount' => 40000,
            'status' => 'pickup_scheduling',
            'pickup_address' => 'Pickup address',
        ]);

        $this->getJson('/api/v1/driver/tasks/deliveries/'.$order->id, [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('data.task.reference_id', 'PR-30001')
            ->assertJsonPath('data.order.order_number', 'PR-30001');

        $this->getJson('/api/v1/driver/tasks/pickups/'.$booking->id, [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('data.task.reference_id', 'SELL30001')
            ->assertJsonPath('data.pickup.booking_number', 'SELL30001');
    }

    public function test_driver_cannot_view_tasks_assigned_to_another_driver(): void
    {
        $token = $this->driverAuthToken(['phone' => '9876543404']);
        $otherDriver = Driver::query()->create([
            'name' => 'Other Driver',
            'phone' => '9876543499',
            'vehicle_type' => 'bike',
            'is_active' => true,
        ]);
        $user = User::factory()->create();

        $order = JewelleryOrder::query()->create([
            'order_number' => 'PR-40001',
            'user_id' => $user->id,
            'driver_id' => $otherDriver->id,
            'subtotal' => 1000,
            'total_amount' => 1000,
            'status' => 'processing',
        ]);

        $this->getJson('/api/v1/driver/tasks/deliveries/'.$order->id, [
            'Authorization' => 'Bearer '.$token,
        ])->assertNotFound();
    }

    public function test_assigning_driver_to_pickup_sets_assigned_timestamp(): void
    {
        $driver = Driver::query()->create([
            'name' => 'Pickup Driver',
            'phone' => '9876543410',
            'vehicle_type' => 'bike',
            'is_active' => true,
        ]);
        $user = User::factory()->create();

        $booking = OldGoldBooking::query()->create([
            'booking_number' => 'SELL40001',
            'user_id' => $user->id,
            'metal_type' => 'gold',
            'purity' => '22K',
            'estimated_weight_grams' => 5,
            'quoted_amount' => 25000,
            'status' => 'accepted',
        ]);

        $booking->update(['driver_id' => $driver->id]);
        $booking->refresh();

        $this->assertSame($driver->id, $booking->driver_id);
        $this->assertNotNull($booking->driver_assigned_at);
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
