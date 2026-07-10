<?php

namespace Tests\Feature;

use App\Models\Driver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unregistered_phone_cannot_request_driver_otp(): void
    {
        $this->postJson('/api/v1/driver/login/send-otp', [
            'phone' => '9876543210',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_active_driver_can_login_with_otp(): void
    {
        config(['otp.expose_in_response' => true]);

        $driver = Driver::query()->create([
            'name' => 'Rahul Driver',
            'phone' => '9876543210',
            'vehicle_type' => 'bike',
            'vehicle_number' => 'MH12AB1234',
            'is_active' => true,
        ]);

        $send = $this->postJson('/api/v1/driver/login/send-otp', [
            'phone' => '9876543210',
        ]);

        $send->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.phone', '9876543210')
            ->assertJsonStructure(['data' => ['otp', 'resend_after_seconds']]);

        $verify = $this->postJson('/api/v1/driver/login/verify-otp', [
            'phone' => '9876543210',
            'otp' => $send->json('data.otp'),
        ]);

        $verify->assertOk()
            ->assertJsonPath('data.driver.id', $driver->id)
            ->assertJsonPath('data.driver.name', 'Rahul Driver')
            ->assertJsonStructure(['data' => ['token', 'driver']]);

        $token = $verify->json('data.token');

        $this->getJson('/api/v1/driver/profile', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('data.driver.phone', '9876543210');

        $this->postJson('/api/v1/driver/logout', [], [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();
    }

    public function test_inactive_driver_cannot_login(): void
    {
        Driver::query()->create([
            'name' => 'Inactive Driver',
            'phone' => '9876543211',
            'vehicle_type' => 'car',
            'is_active' => false,
        ]);

        $this->postJson('/api/v1/driver/login/send-otp', [
            'phone' => '9876543211',
        ])->assertStatus(403);
    }

    public function test_user_token_cannot_access_driver_profile(): void
    {
        $user = \App\Models\User::factory()->create([
            'phone' => '9876543212',
            'mpin' => '1234',
        ]);

        $token = $user->createToken('mobile-app')->plainTextToken;

        $this->getJson('/api/v1/driver/profile', [
            'Authorization' => 'Bearer '.$token,
        ])->assertUnauthorized();
    }
}
