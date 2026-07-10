<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\OldGoldBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DriverPickupApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_pickup_config_returns_failure_reasons_and_statuses(): void
    {
        $token = $this->driverAuthToken(['phone' => '9876543600']);

        $this->getJson('/api/v1/driver/pickups/config', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('data.otp_length', 4)
            ->assertJsonStructure([
                'data' => [
                    'otp_length',
                    'failure_reasons' => [['value', 'label']],
                    'statuses' => [
                        'processing' => ['label', 'color'],
                        'verified' => ['label', 'color'],
                        'proof_uploaded' => ['label', 'color'],
                        'collected' => ['label', 'color'],
                        'cancelled' => ['label', 'color'],
                    ],
                ],
            ]);
    }

    public function test_driver_can_view_pickup_detail_with_actions(): void
    {
        $token = $this->driverAuthToken(['phone' => '9876543601']);
        $driver = Driver::query()->where('phone', '9876543601')->firstOrFail();
        $booking = $this->createAssignedPickup($driver);

        $this->getJson('/api/v1/driver/tasks/pickups/'.$booking->id, [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('data.task.driver_pickup_status', 'processing')
            ->assertJsonPath('data.pickup.booking_number', $booking->booking_number)
            ->assertJsonPath('data.pickup.driver_pickup_status', 'processing')
            ->assertJsonPath('data.pickup.customer.name', 'Alexander Vance')
            ->assertJsonPath('data.pickup.customer.phone_display', '+91 9834522802')
            ->assertJsonPath('data.pickup.sell_from.label', 'At Home')
            ->assertJsonPath('data.pickup.available_actions.0.key', 'verify_customer')
            ->assertJsonCount(2, 'data.pickup.available_actions')
            ->assertJsonMissingPath('data.pickup.delivery_otp');
    }

    public function test_driver_pickup_routes_accept_booking_number(): void
    {
        $token = $this->driverAuthToken(['phone' => '9876543611']);
        $driver = Driver::query()->where('phone', '9876543611')->firstOrFail();
        $booking = $this->createAssignedPickup($driver, [
            'booking_number' => 'SELL96309',
        ]);

        $this->getJson('/api/v1/driver/tasks/pickups/'.$booking->booking_number, [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('data.pickup.booking_number', 'SELL96309');

        $this->postJson('/api/v1/driver/tasks/pickups/'.$booking->booking_number.'/verify-customer', [
            'confirmed' => true,
        ], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('data.pickup.driver_pickup_status', 'verified');
    }

    public function test_driver_can_complete_full_pickup_flow(): void
    {
        Storage::fake('public');

        $token = $this->driverAuthToken(['phone' => '9876543602']);
        $driver = Driver::query()->where('phone', '9876543602')->firstOrFail();
        $booking = $this->createAssignedPickup($driver, [
            'delivery_otp' => '4321',
        ]);

        $this->postJson('/api/v1/driver/tasks/pickups/'.$booking->id.'/verify-customer', [
            'confirmed' => true,
        ], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('data.pickup.driver_pickup_status', 'verified');

        $this->post('/api/v1/driver/tasks/pickups/'.$booking->id.'/upload-proof', [
            'proof_images' => [
                UploadedFile::fake()->image('proof-1.jpg'),
                UploadedFile::fake()->image('proof-2.jpg'),
            ],
        ], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('data.pickup.driver_pickup_status', 'proof_uploaded')
            ->assertJsonCount(2, 'data.pickup.verification.proof_image_urls');

        $this->postJson('/api/v1/driver/tasks/pickups/'.$booking->id.'/verify-otp', [
            'otp' => '4321',
        ], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('data.pickup.driver_pickup_status', 'collected')
            ->assertJsonPath('message', 'Jewellery collected successfully.');

        $booking->refresh();
        $this->assertSame('picked_up', $booking->status);
        $this->assertNotNull($booking->picked_up_at);
        $this->assertNotNull($booking->customer_verified_at);
        $this->assertCount(2, $booking->pickup_proof_images);
    }

    public function test_verify_otp_rejects_invalid_code(): void
    {
        $token = $this->driverAuthToken(['phone' => '9876543603']);
        $driver = Driver::query()->where('phone', '9876543603')->firstOrFail();
        $booking = $this->createAssignedPickup($driver, [
            'customer_verified_at' => now(),
            'pickup_proof_images' => ['pickup-proofs/test.jpg'],
            'delivery_otp' => '1111',
        ]);

        $this->postJson('/api/v1/driver/tasks/pickups/'.$booking->id.'/verify-otp', [
            'otp' => '9999',
        ], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['otp']);
    }

    public function test_driver_can_mark_pickup_as_unable_to_pickup(): void
    {
        $token = $this->driverAuthToken(['phone' => '9876543604']);
        $driver = Driver::query()->where('phone', '9876543604')->firstOrFail();
        $booking = $this->createAssignedPickup($driver);

        $this->postJson('/api/v1/driver/tasks/pickups/'.$booking->id.'/unable-to-pickup', [
            'reason' => 'customer_unavailable',
        ], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('data.pickup.driver_pickup_status', 'cancelled')
            ->assertJsonPath('data.pickup.pickup_failure_reason', 'customer_unavailable');

        $booking->refresh();
        $this->assertSame('cancelled', $booking->status);
    }

    public function test_driver_cannot_update_pickup_assigned_to_another_driver(): void
    {
        $token = $this->driverAuthToken(['phone' => '9876543605']);
        $otherDriver = Driver::query()->create([
            'name' => 'Other Driver',
            'phone' => '9876543699',
            'vehicle_type' => 'bike',
            'is_active' => true,
        ]);
        $booking = $this->createAssignedPickup($otherDriver);

        $this->postJson('/api/v1/driver/tasks/pickups/'.$booking->id.'/verify-customer', [
            'confirmed' => true,
        ], [
            'Authorization' => 'Bearer '.$token,
        ])->assertForbidden();
    }

    public function test_assigning_driver_sets_booking_status_to_processing(): void
    {
        $driver = Driver::query()->create([
            'name' => 'Pickup Driver',
            'phone' => '9876543610',
            'vehicle_type' => 'bike',
            'is_active' => true,
        ]);
        $user = User::factory()->create();

        $booking = OldGoldBooking::query()->create([
            'booking_number' => 'SELL90001',
            'user_id' => $user->id,
            'metal_type' => 'gold',
            'purity' => '22K',
            'quoted_amount' => 50000,
            'status' => 'accepted',
            'pickup_address' => 'Test address',
        ]);

        $booking->update(['driver_id' => $driver->id]);
        $booking->refresh();

        $this->assertSame('processing', $booking->status);
        $this->assertNotNull($booking->driver_assigned_at);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createAssignedPickup(Driver $driver, array $attributes = []): OldGoldBooking
    {
        $user = User::factory()->create([
            'name' => 'Alexander Vance',
            'phone' => '9834522802',
        ]);

        $booking = OldGoldBooking::query()->create(array_merge([
            'booking_number' => 'SELL'.fake()->unique()->numerify('#####'),
            'user_id' => $user->id,
            'metal_type' => 'gold',
            'purity' => '22K',
            'item_name' => 'Gold Jewellery',
            'estimated_weight_grams' => 25,
            'quoted_amount' => 160625,
            'identity_owner' => 'own_name',
            'sell_location' => 'at_home',
            'driver_id' => $driver->id,
            'status' => 'accepted',
            'pickup_name' => 'Alexander Vance',
            'pickup_phone' => '9834522802',
            'pickup_address' => '4517 Washington Ave., Manchester',
        ], $attributes));

        return $booking->refresh();
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
