<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['otp.expose_in_response' => true]);
    }

    public function test_login_send_otp_returns_otp_and_verify_mpin_flow(): void
    {
        $user = User::factory()->create([
            'phone' => '9876543210',
            'mpin' => '1234',
        ]);

        $send = $this->postJson('/api/v1/login/send-otp', [
            'phone' => '9876543210',
        ]);

        $send->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.requires_otp_verification', true)
            ->assertJsonPath('data.has_mpin', true)
            ->assertJsonPath('data.next_api', '/api/v1/login/verify-otp')
            ->assertJsonStructure(['data' => ['otp']]);

        $otp = $send->json('data.otp');

        $verify = $this->postJson('/api/v1/login/verify-otp', [
            'phone' => '9876543210',
            'otp' => $otp,
        ]);

        $verify->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.requires_mpin', true)
            ->assertJsonPath('data.user.name', $user->name);

        $login = $this->postJson('/api/v1/login/mpin', [
            'phone' => '9876543210',
            'mpin' => '1234',
        ]);

        $login->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Login successful.')
            ->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_login_verify_otp_with_mpin_logs_in_immediately(): void
    {
        User::factory()->create([
            'phone' => '9876543210',
            'mpin' => '1234',
        ]);

        $send = $this->postJson('/api/v1/login/send-otp', [
            'phone' => '9876543210',
        ]);

        $otp = $send->json('data.otp');

        $response = $this->postJson('/api/v1/login/verify-otp', [
            'phone' => '9876543210',
            'otp' => $otp,
            'mpin' => '1234',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_direct_login_returns_mpin(): void
    {
        User::factory()->create([
            'phone' => '9876543210',
            'mpin' => '1234',
        ]);

        $response = $this->postJson('/api/v1/login', [
            'phone' => '9876543210',
            'mpin' => '1234',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.mpin', '1234')
            ->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_login_send_otp_rejects_unregistered_phone(): void
    {
        $response = $this->postJson('/api/v1/login/send-otp', [
            'phone' => '9876543210',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.errors.phone', fn ($errors) => ! empty($errors));
    }
}
