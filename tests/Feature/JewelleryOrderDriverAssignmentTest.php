<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\JewelleryOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JewelleryOrderDriverAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_driver_appears_in_assignment_options(): void
    {
        $activeDriver = Driver::query()->create([
            'name' => 'Active Driver',
            'phone' => '9876543212',
            'primary_residence' => 'Mumbai',
            'vehicle_type' => 'bike',
            'is_active' => true,
            'is_online' => true,
        ]);

        Driver::query()->create([
            'name' => 'Inactive Driver',
            'phone' => '9876543213',
            'primary_residence' => 'Mumbai',
            'vehicle_type' => 'bike',
            'is_active' => false,
            'is_online' => false,
        ]);

        $options = Driver::assignmentOptions();

        $this->assertArrayHasKey($activeDriver->id, $options);
        $this->assertCount(1, $options);
        $this->assertStringContainsString('Online', $options[$activeDriver->id]);
    }

    public function test_new_driver_defaults_to_active_and_online(): void
    {
        $driver = Driver::query()->create([
            'name' => 'Default Driver',
            'phone' => '9876543214',
            'primary_residence' => 'Mumbai',
            'vehicle_type' => 'bike',
        ]);

        $this->assertTrue($driver->is_active);
        $this->assertTrue($driver->is_online);
    }

    public function test_assigning_driver_sets_assigned_timestamp(): void
    {
        $user = User::factory()->create();
        $driver = Driver::query()->create([
            'name' => 'Rahul Driver',
            'phone' => '9876543210',
            'primary_residence' => 'Mumbai',
            'vehicle_type' => 'bike',
            'vehicle_number' => 'MH01AB1234',
            'is_active' => true,
        ]);

        $order = JewelleryOrder::query()->create([
            'order_number' => 'HOX99999',
            'user_id' => $user->id,
            'subtotal' => 10000,
            'total_amount' => 10000,
            'status' => 'pending',
        ]);

        $order->update(['driver_id' => $driver->id]);

        $order->refresh();

        $this->assertSame($driver->id, $order->driver_id);
        $this->assertSame('processing', $order->status);
        $this->assertNotNull($order->driver_assigned_at);
    }

    public function test_assigning_driver_does_not_change_completed_order_status(): void
    {
        $user = User::factory()->create();
        $driver = Driver::query()->create([
            'name' => 'Rahul Driver',
            'phone' => '9876543212',
            'primary_residence' => 'Mumbai',
            'vehicle_type' => 'bike',
            'is_active' => true,
        ]);

        $order = JewelleryOrder::query()->create([
            'order_number' => 'HOX99997',
            'user_id' => $user->id,
            'subtotal' => 10000,
            'total_amount' => 10000,
            'status' => 'completed',
        ]);

        $order->update(['driver_id' => $driver->id]);
        $order->refresh();

        $this->assertSame('completed', $order->status);
    }

    public function test_removing_driver_clears_assigned_timestamp(): void
    {
        $user = User::factory()->create();
        $driver = Driver::query()->create([
            'name' => 'Rahul Driver',
            'phone' => '9876543211',
            'primary_residence' => 'Mumbai',
            'vehicle_type' => 'bike',
            'vehicle_number' => 'MH01AB1235',
            'is_active' => true,
        ]);

        $order = JewelleryOrder::query()->create([
            'order_number' => 'HOX99998',
            'user_id' => $user->id,
            'subtotal' => 10000,
            'total_amount' => 10000,
            'status' => 'processing',
            'driver_id' => $driver->id,
        ]);

        $order->refresh();
        $this->assertNotNull($order->driver_assigned_at);

        $order->update(['driver_id' => null]);
        $order->refresh();

        $this->assertNull($order->driver_id);
        $this->assertNull($order->driver_assigned_at);
    }
}
