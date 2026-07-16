<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_flow_completes_successfully(): void
    {
        config([
            'otp.expose_in_response' => true,
            'otp.resend_after_seconds' => 0,
        ]);

        $send = $this->postJson('/api/v1/register/send-otp', [
            'phone' => '9876543210',
        ]);

        $send->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['message', 'data' => ['resend_after_seconds', 'otp']]);

        $otp = $send->json('data.otp');

        $verify = $this->postJson('/api/v1/register/verify-otp', [
            'phone' => '9876543210',
            'otp' => $otp,
        ]);

        $verify->assertOk()
            ->assertJsonPath('data.already_registered', false)
            ->assertJsonStructure(['data' => ['registration_token', 'token', 'phone', 'expires_in_seconds', 'mpin_length']]);

        $registrationToken = $verify->json('data.registration_token');

        $details = $this->postJson('/api/v1/register/details', [
            'registration_token' => $registrationToken,
            'name' => 'Rahul Sharma',
        ]);

        $details->assertOk()
            ->assertJsonPath('data.name', 'Rahul Sharma')
            ->assertJsonPath('data.phone', '9876543210');

        $complete = $this->postJson('/api/v1/register/mpin', [
            'registration_token' => $registrationToken,
            'mpin' => '1234',
        ]);

        $complete->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'mpin', 'mpin_length', 'user' => ['id', 'name', 'phone', 'referral_code']]])
            ->assertJsonPath('data.user.name', 'Rahul Sharma')
            ->assertJsonPath('data.mpin', '1234')
            ->assertJsonPath('message', 'M-PIN created successfully.');

        $this->assertDatabaseHas('users', [
            'phone' => '9876543210',
            'name' => 'Rahul Sharma',
        ]);
    }

    public function test_newly_registered_user_gets_mpin_on_repeat_verify_otp(): void
    {
        config([
            'otp.expose_in_response' => true,
            'otp.resend_after_seconds' => 0,
        ]);

        $send = $this->postJson('/api/v1/register/send-otp', [
            'phone' => '9123456789',
        ]);

        $verify = $this->postJson('/api/v1/register/verify-otp', [
            'phone' => '9123456789',
            'otp' => $send->json('data.otp'),
        ]);

        $token = $verify->json('data.registration_token');

        $this->postJson('/api/v1/register/details', [
            'registration_token' => $token,
            'name' => 'New User',
        ])->assertOk();

        $this->postJson('/api/v1/register/mpin', [
            'registration_token' => $token,
            'mpin' => '4321',
        ])->assertCreated();

        $sendAgain = $this->postJson('/api/v1/register/send-otp', [
            'phone' => '9123456789',
        ]);

        $verifyAgain = $this->postJson('/api/v1/register/verify-otp', [
            'phone' => '9123456789',
            'otp' => $sendAgain->json('data.otp'),
        ]);

        $verifyAgain->assertOk()
            ->assertJsonPath('data.already_registered', true)
            ->assertJsonPath('data.mpin', '4321')
            ->assertJsonPath('data.mpin_legacy_hashed', false)
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_send_otp_allows_already_registered_phone(): void
    {
        config([
            'otp.expose_in_response' => true,
            'otp.resend_after_seconds' => 0,
        ]);

        User::factory()->create([
            'phone' => '9876543210',
            'mpin' => '1234',
        ]);

        $response = $this->postJson('/api/v1/register/send-otp', [
            'phone' => '9876543210',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.already_registered', true)
            ->assertJsonStructure(['message', 'data' => ['otp']]);
    }

    public function test_verify_otp_for_registered_user_returns_token_and_mpin(): void
    {
        config([
            'otp.expose_in_response' => true,
            'otp.resend_after_seconds' => 0,
        ]);

        $user = User::factory()->create([
            'phone' => '9876543210',
            'mpin' => '1234',
        ]);

        $send = $this->postJson('/api/v1/register/send-otp', [
            'phone' => '9876543210',
        ]);

        $verify = $this->postJson('/api/v1/register/verify-otp', [
            'phone' => '9876543210',
            'otp' => $send->json('data.otp'),
        ]);

        $verify->assertOk()
            ->assertJsonPath('data.already_registered', true)
            ->assertJsonPath('data.requires_mpin', false)
            ->assertJsonPath('data.phone', '9876543210')
            ->assertJsonPath('data.mpin', '1234')
            ->assertJsonPath('data.user.name', $user->name)
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_verify_otp_for_registered_user_logs_in_with_mpin(): void
    {
        config([
            'otp.expose_in_response' => true,
            'otp.resend_after_seconds' => 0,
        ]);

        $user = User::factory()->create([
            'phone' => '9876543210',
            'mpin' => '1234',
        ]);

        $send = $this->postJson('/api/v1/register/send-otp', [
            'phone' => '9876543210',
        ]);

        $verify = $this->postJson('/api/v1/register/verify-otp', [
            'phone' => '9876543210',
            'otp' => $send->json('data.otp'),
            'mpin' => '1234',
        ]);

        $verify->assertOk()
            ->assertJsonPath('data.already_registered', true)
            ->assertJsonPath('data.mpin', '1234')
            ->assertJsonPath('data.user.name', $user->name)
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_verify_otp_for_registered_user_can_login_mpin_on_next_step(): void
    {
        config([
            'otp.expose_in_response' => true,
            'otp.resend_after_seconds' => 0,
        ]);

        User::factory()->create([
            'phone' => '9876543210',
            'mpin' => '1234',
        ]);

        $send = $this->postJson('/api/v1/register/send-otp', [
            'phone' => '9876543210',
        ]);

        $verify = $this->postJson('/api/v1/register/verify-otp', [
            'phone' => '9876543210',
            'otp' => $send->json('data.otp'),
        ]);

        $verify->assertOk()
            ->assertJsonPath('data.requires_mpin', false)
            ->assertJsonPath('data.mpin', '1234')
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_validate_referral_returns_referrer_name(): void
    {
        $referrer = User::factory()->create([
            'referral_code' => 'HOXTAN01',
        ]);

        $response = $this->postJson('/api/v1/register/validate-referral', [
            'referral_code' => 'HOXTAN01',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.referrer_name', $referrer->name)
            ->assertJsonPath('message', 'Referral code applied successfully.');
    }

    public function test_validate_referral_returns_json_for_invalid_code(): void
    {
        $response = $this->postJson('/api/v1/register/validate-referral', [
            'referral_code' => 'INVALID1',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('message', 'Invalid referral code.');
    }

    public function test_verify_otp_stores_fcm_token_for_existing_user(): void
    {
        config([
            'otp.expose_in_response' => true,
            'otp.resend_after_seconds' => 0,
        ]);

        $user = User::factory()->create([
            'phone' => '9090909090',
            'mpin' => '1234',
        ]);

        $send = $this->postJson('/api/v1/register/send-otp', [
            'phone' => '9090909090',
        ]);

        $verify = $this->postJson('/api/v1/register/verify-otp', [
            'phone' => '9090909090',
            'otp' => $send->json('data.otp'),
            'fcm_token' => 'test-fcm-token-existing-user',
            'platform' => 'android',
        ]);

        $verify->assertOk()
            ->assertJsonPath('data.fcm_token_registered', true);

        $this->assertDatabaseHas('device_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'fcm_token' => 'test-fcm-token-existing-user',
            'platform' => 'android',
        ]);
    }

    public function test_verify_otp_keeps_fcm_token_and_saves_on_registration_complete(): void
    {
        config([
            'otp.expose_in_response' => true,
            'otp.resend_after_seconds' => 0,
        ]);

        $send = $this->postJson('/api/v1/register/send-otp', [
            'phone' => '9012345678',
        ]);

        $verify = $this->postJson('/api/v1/register/verify-otp', [
            'phone' => '9012345678',
            'otp' => $send->json('data.otp'),
            'fcm_token' => 'test-fcm-token-new-user',
            'platform' => 'ios',
        ]);

        $verify->assertOk()
            ->assertJsonPath('data.already_registered', false)
            ->assertJsonPath('data.fcm_token_received', true);

        $registrationToken = $verify->json('data.registration_token');

        $this->postJson('/api/v1/register/details', [
            'registration_token' => $registrationToken,
            'name' => 'Fcm User',
        ])->assertOk();

        $complete = $this->postJson('/api/v1/register/mpin', [
            'registration_token' => $registrationToken,
            'mpin' => '4321',
        ]);

        $complete->assertCreated()
            ->assertJsonPath('data.fcm_token_registered', true);

        $userId = $complete->json('data.user.id');

        $this->assertDatabaseHas('device_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $userId,
            'fcm_token' => 'test-fcm-token-new-user',
            'platform' => 'ios',
        ]);
    }

    public function test_verify_otp_allows_empty_fcm_token(): void
    {
        config([
            'otp.expose_in_response' => true,
            'otp.resend_after_seconds' => 0,
        ]);

        $send = $this->postJson('/api/v1/register/send-otp', [
            'phone' => '9080706050',
        ]);

        $verify = $this->postJson('/api/v1/register/verify-otp', [
            'phone' => '9080706050',
            'otp' => $send->json('data.otp'),
            'fcm_token' => '',
        ]);

        $verify->assertOk()
            ->assertJsonPath('data.already_registered', false)
            ->assertJsonPath('data.fcm_token_received', false)
            ->assertJsonPath('data.fcm_token_registered', false)
            ->assertJsonPath('data.fcm_token_skipped_reason', 'empty_or_missing');
    }
}
