<?php

namespace Tests\Feature;

use App\Models\Driver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
            ->assertJsonPath('data.driver.phone', '9876543210')
            ->assertJsonPath('data.driver.profile_image_url', null);
    }

    public function test_driver_profile_returns_profile_image_url(): void
    {
        config(['otp.expose_in_response' => true]);

        $driver = Driver::query()->create([
            'name' => 'Rahul Driver',
            'phone' => '9876543220',
            'vehicle_type' => 'bike',
            'vehicle_number' => 'MH12AB1234',
            'profile_image' => 'driver-profiles/avatar.jpg',
            'is_active' => true,
        ]);

        $send = $this->postJson('/api/v1/driver/login/send-otp', [
            'phone' => '9876543220',
        ]);

        $verify = $this->postJson('/api/v1/driver/login/verify-otp', [
            'phone' => '9876543220',
            'otp' => $send->json('data.otp'),
        ]);

        $token = $verify->json('data.token');

        $this->getJson('/api/v1/driver/profile', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('data.driver.profile_image_url', fn ($url) => filled($url))
            ->assertJsonPath('data.driver.image_url', fn ($url) => filled($url));

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

    public function test_driver_can_update_profile(): void
    {
        $token = $this->driverAuthToken([
            'name' => 'Aman Kumar',
            'phone' => '9876543230',
            'email' => 'old@hoxtan.com',
            'primary_residence' => 'Indore, Madhya Pradesh',
        ]);

        $this->putJson('/api/v1/driver/profile', [
            'name' => 'Aman Kumar',
            'email' => 'kumar.a@hoxtan.com',
            'primary_residence' => 'Bhopal, Madhya Pradesh',
        ], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('data.driver.name', 'Aman Kumar')
            ->assertJsonPath('data.driver.email', 'kumar.a@hoxtan.com')
            ->assertJsonPath('data.driver.primary_residence', 'Bhopal, Madhya Pradesh')
            ->assertJsonPath('data.driver.phone', '9876543230')
            ->assertJsonPath('data.driver.phone_verified', true);
    }

    public function test_driver_can_update_profile_photo(): void
    {
        Storage::fake('public');
        $token = $this->driverAuthToken([
            'name' => 'Aman Kumar',
            'phone' => '9876543231',
        ]);

        $this->post('/api/v1/driver/profile', [
            'image' => UploadedFile::fake()->image('avatar.jpg'),
        ], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('data.driver.profile_image_url', fn ($url) => filled($url));

        $driver = Driver::query()->where('phone', '9876543231')->firstOrFail();
        Storage::disk('public')->assertExists($driver->profile_image);
    }

    public function test_driver_can_toggle_availability(): void
    {
        $token = $this->driverAuthToken([
            'name' => 'Aman Kumar',
            'phone' => '9876543232',
            'is_online' => false,
        ]);

        $this->putJson('/api/v1/driver/availability', [
            'is_online' => true,
        ], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('data.driver.is_online', true)
            ->assertJsonPath('data.driver.availability_status', 'online')
            ->assertJsonPath('data.driver.availability_label', 'Go Offline')
            ->assertJsonPath('message', 'You are now online.');

        $this->putJson('/api/v1/driver/availability', [
            'is_online' => false,
        ], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('data.driver.is_online', false)
            ->assertJsonPath('data.driver.availability_status', 'offline')
            ->assertJsonPath('data.driver.availability_label', 'Go Online')
            ->assertJsonPath('message', 'You are now offline.');
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
