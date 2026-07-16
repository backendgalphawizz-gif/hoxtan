<?php

namespace Tests\Feature;

use App\Models\OldGoldBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SellJewelleryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_estimate_submit_and_track_sell_request(): void
    {
        Storage::fake('public');

        $user = $this->userWithTransactionKyc([
            'phone' => '9876543211',
            'mpin' => '1234',
        ]);

        Sanctum::actingAs($user);

        $config = $this->getJson('/api/v1/sell-jewellery/config');

        $config->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'metal_types',
                    'purities',
                    'identity_owners',
                    'sell_locations',
                    'document_types',
                    'status_filters',
                    'rates' => ['gold', 'silver'],
                ],
            ]);

        $estimate = $this->postJson('/api/v1/sell-jewellery/estimate', [
            'metal_type' => 'gold',
            'weight_grams' => 10,
            'purity' => '22K',
        ]);

        $estimate->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.estimate.metal_type', 'gold')
            ->assertJsonPath('data.estimate.purity', '22K')
            ->assertJsonStructure([
                'data' => [
                    'estimate' => [
                        'weight_grams',
                        'rate_per_gram',
                        'estimated_value',
                        'estimated_value_display',
                    ],
                ],
            ]);

        $create = $this->post('/api/v1/sell-jewellery/requests', [
            'metal_type' => 'gold',
            'weight_grams' => 10,
            'purity' => '22K',
            'identity_owner' => 'own_name',
            'sell_location' => 'at_home',
            'confirmed' => true,
            'full_name' => 'Test User',
            'address_line' => '12 MG Road',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'pincode' => '400001',
            'phone' => '9876543211',
            'selfie' => UploadedFile::fake()->image('selfie.jpg'),
            'purchase_receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ]);

        $create->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.request.status', 'pending')
            ->assertJsonPath('data.request.metal_type', 'gold')
            ->assertJsonStructure([
                'data' => [
                    'request' => [
                        'booking_number_display',
                        'delivery_otp',
                        'documents',
                        'tracking' => [
                            ['key', 'label', 'completed', 'current', 'completed_at'],
                        ],
                    ],
                ],
            ]);

        $deliveryOtp = $create->json('data.request.delivery_otp');
        $this->assertNotNull($deliveryOtp);
        $this->assertMatchesRegularExpression('/^\d{4}$/', $deliveryOtp);

        $requestId = $create->json('data.request.id');

        $list = $this->getJson('/api/v1/sell-jewellery/requests?status=pending');

        $list->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.requests.0.id', $requestId)
            ->assertJsonPath('data.requests.0.delivery_otp', $deliveryOtp);

        $show = $this->getJson("/api/v1/sell-jewellery/requests/{$requestId}");

        $show->assertOk()
            ->assertJsonPath('data.request.delivery_otp', $deliveryOtp)
            ->assertJsonPath('data.request.tracking.0.key', 'pending')
            ->assertJsonPath('data.request.tracking.0.current', true)
            ->assertJsonPath('data.request.documents.1.uploaded', true);
    }

    public function test_sell_request_show_includes_assigned_driver_details(): void
    {
        $user = $this->userWithTransactionKyc([
            'phone' => '9876543213',
            'mpin' => '1234',
        ]);
        Sanctum::actingAs($user);

        $booking = OldGoldBooking::query()->create([
            'booking_number' => 'SELLDRIVER1',
            'user_id' => $user->id,
            'metal_type' => 'gold',
            'purity' => '22K',
            'estimated_weight_grams' => 10,
            'quoted_amount' => 50000,
            'status' => 'pending',
            'pickup_address' => '12 MG Road, Mumbai',
            'pickup_name' => 'Test User',
            'pickup_phone' => '9876543213',
        ]);

        $this->getJson("/api/v1/sell-jewellery/requests/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.request.driver', null);

        $driver = \App\Models\Driver::query()->create([
            'name' => 'Suresh Pickup',
            'phone' => '9911223344',
            'vehicle_type' => 'van',
            'vehicle_number' => 'MH14CD5678',
            'is_active' => true,
            'is_online' => true,
        ]);

        $booking->update([
            'driver_id' => $driver->id,
            'driver_assigned_at' => now(),
        ]);

        $this->getJson("/api/v1/sell-jewellery/requests/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.request.driver_id', $driver->id)
            ->assertJsonPath('data.request.driver.name', 'Suresh Pickup')
            ->assertJsonPath('data.request.driver.phone', '9911223344')
            ->assertJsonPath('data.request.driver.phone_display', '+91 9911223344')
            ->assertJsonPath('data.request.driver.vehicle_type', 'van')
            ->assertJsonPath('data.request.driver.vehicle_number', 'MH14CD5678');
    }

    public function test_id_proof_required_only_when_jewellery_is_in_someone_elses_name(): void
    {
        Storage::fake('public');

        $user = $this->userWithTransactionKyc([
            'phone' => '9876543212',
            'mpin' => '1234',
        ]);

        Sanctum::actingAs($user);

        $basePayload = [
            'metal_type' => 'gold',
            'weight_grams' => 8,
            'purity' => '22K',
            'sell_location' => 'at_home',
            'confirmed' => true,
            'full_name' => 'Test User',
            'address_line' => '12 MG Road',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'pincode' => '400001',
            'phone' => '9876543212',
            'selfie' => UploadedFile::fake()->image('selfie.jpg'),
            'purchase_receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ];

        $this->post('/api/v1/sell-jewellery/requests', array_merge($basePayload, [
            'identity_owner' => 'own_name',
        ]))->assertCreated();

        $this->post('/api/v1/sell-jewellery/requests', array_merge($basePayload, [
            'identity_owner' => 'someone_else',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['id_proof']);

        $this->post('/api/v1/sell-jewellery/requests', array_merge($basePayload, [
            'identity_owner' => 'someone_else',
            'id_proof' => UploadedFile::fake()->image('aadhar.jpg'),
        ]))->assertCreated();
    }

    public function test_recent_sold_returns_completed_bookings(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        OldGoldBooking::query()->create([
            'booking_number' => 'SELL12345',
            'user_id' => $user->id,
            'metal_type' => 'gold',
            'purity' => '24K',
            'estimated_weight_grams' => 5,
            'quoted_amount' => 35000,
            'status' => 'completed',
            'pickup_address' => 'Test Address',
        ]);

        $response = $this->getJson('/api/v1/sell-jewellery/recent');

        $response->assertOk()
            ->assertJsonPath('data.recently_sold.0.booking_number', 'SELL12345')
            ->assertJsonPath('data.recently_sold.0.status', 'completed');
    }
}
