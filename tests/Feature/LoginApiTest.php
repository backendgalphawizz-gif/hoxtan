<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_send_otp_returns_mpin_step_without_sending_otp(): void
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
            ->assertJsonPath('data.requires_mpin', true)
            ->assertJsonPath('data.mpin', '1234')
            ->assertJsonPath('data.has_mpin', true)
            ->assertJsonPath('data.user.name', $user->name)
            ->assertJsonMissing(['data' => ['otp' => true]]);

        $login = $this->postJson('/api/v1/login/mpin', [
            'phone' => '9876543210',
            'mpin' => '1234',
        ]);

        $login->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.mpin', '1234')
            ->assertJsonPath('message', 'Login successful.')
            ->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_login_send_otp_with_mpin_logs_in_immediately(): void
    {
        User::factory()->create([
            'phone' => '9876543210',
            'mpin' => '1234',
        ]);

        $response = $this->postJson('/api/v1/login/send-otp', [
            'phone' => '9876543210',
            'mpin' => '1234',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.mpin', '1234')
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
