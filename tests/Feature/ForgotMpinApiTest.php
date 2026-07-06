<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForgotMpinApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_mpin_flow_completes_successfully(): void
    {
        config([
            'otp.expose_in_response' => true,
            'otp.resend_after_seconds' => 0,
        ]);

        $user = User::factory()->create([
            'phone' => '9876543210',
            'mpin' => '1234',
        ]);

        $send = $this->postJson('/api/v1/forgot-mpin/send-otp', [
            'phone' => '9876543210',
        ]);

        $send->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['message', 'data' => ['otp', 'mpin_length']]);

        $verify = $this->postJson('/api/v1/forgot-mpin/verify-otp', [
            'phone' => '9876543210',
            'otp' => $send->json('data.otp'),
        ]);

        $verify->assertOk()
            ->assertJsonPath('data.requires_mpin', true)
            ->assertJsonStructure(['data' => ['reset_token', 'expires_in_seconds']]);

        $reset = $this->postJson('/api/v1/forgot-mpin/set-mpin', [
            'reset_token' => $verify->json('data.reset_token'),
            'mpin' => '5678',
        ]);

        $reset->assertOk()
            ->assertJsonPath('message', 'M-PIN created successfully.')
            ->assertJsonPath('data.mpin', '5678');

        $user->refresh();
        $this->assertTrue($user->verifyMpin('5678'));
    }

    public function test_forgot_mpin_send_otp_rejects_unregistered_phone(): void
    {
        $response = $this->postJson('/api/v1/forgot-mpin/send-otp', [
            'phone' => '9876543210',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.errors.phone', fn ($errors) => ! empty($errors));
    }
}
