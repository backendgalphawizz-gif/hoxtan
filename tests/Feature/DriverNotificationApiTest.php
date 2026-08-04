<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\DriverNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverNotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_can_list_mark_read_and_mark_all_read_notifications(): void
    {
        $token = $this->driverAuthToken([
            'name' => 'Rahul Driver',
            'phone' => '9876543299',
        ]);

        $driver = Driver::query()->where('phone', '9876543299')->firstOrFail();

        DriverNotification::query()->create([
            'driver_id' => $driver->id,
            'title' => 'Delivery Successfully Completed',
            'body' => 'Order #PR-78451236 has been delivered successfully.',
            'type' => 'delivery_update',
            'data' => [
                'task_type' => 'delivery',
                'order_id' => '55',
                'screen' => 'driver_delivery_detail',
            ],
            'read_at' => now()->subDay(),
            'created_at' => now()->subDay(),
        ]);

        // Created last so it is first in latest(id) list.
        $unread = DriverNotification::query()->create([
            'driver_id' => $driver->id,
            'title' => 'New Assigned Order',
            'body' => 'A gold jewellery pickup has been assigned near Whitefield.',
            'type' => 'new_assigned_order',
            'data' => [
                'task_type' => 'pickup',
                'booking_id' => '10',
                'screen' => 'driver_pickup_detail',
            ],
            'created_at' => now(),
        ]);

        $auth = ['Authorization' => 'Bearer '.$token];

        $this->getJson('/api/v1/driver/notifications', $auth)
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonPath('data.notifications.0.category_label', 'NEW ASSIGNED ORDER')
            ->assertJsonPath('data.notifications.0.is_read', false)
            ->assertJsonPath('data.notifications.1.category_label', 'DELIVERY UPDATE')
            ->assertJsonPath('data.sections.0.key', 'today')
            ->assertJsonPath('data.sections.1.key', 'recent')
            ->assertJsonStructure([
                'data' => [
                    'notifications' => [
                        [
                            'id',
                            'title',
                            'body',
                            'type',
                            'category',
                            'category_label',
                            'is_read',
                            'data',
                            'created_at',
                            'created_at_display',
                            'relative_time',
                            'section',
                        ],
                    ],
                    'sections',
                    'meta',
                ],
            ]);

        $this->getJson('/api/v1/driver/notifications/unread-count', $auth)
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);

        $this->postJson('/api/v1/driver/notifications/'.$unread->id.'/read', [], $auth)
            ->assertOk()
            ->assertJsonPath('data.notification.is_read', true)
            ->assertJsonPath('data.unread_count', 0);

        DriverNotification::query()->create([
            'driver_id' => $driver->id,
            'title' => 'Pickup Scheduled Today',
            'body' => 'Gold jewellery pickup for Sell Request #SELL78210 is scheduled before 5:00 PM.',
            'type' => 'pickup_reminder',
        ]);

        $this->postJson('/api/v1/driver/notifications/read-all', [], $auth)
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0)
            ->assertJsonPath('data.updated', 1);

        $this->assertEquals(0, DriverNotification::query()
            ->where('driver_id', $driver->id)
            ->whereNull('read_at')
            ->count());
    }

    public function test_driver_cannot_read_another_drivers_notification(): void
    {
        $token = $this->driverAuthToken([
            'name' => 'Driver A',
            'phone' => '9876543288',
        ]);

        $other = Driver::query()->create([
            'name' => 'Driver B',
            'phone' => '9876543277',
            'vehicle_type' => 'bike',
            'is_active' => true,
        ]);

        $notification = DriverNotification::query()->create([
            'driver_id' => $other->id,
            'title' => 'Hidden',
            'body' => 'Should not be readable',
            'type' => 'delivery_update',
        ]);

        $this->postJson('/api/v1/driver/notifications/'.$notification->id.'/read', [], [
            'Authorization' => 'Bearer '.$token,
        ])->assertNotFound();
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
