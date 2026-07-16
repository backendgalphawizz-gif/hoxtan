<?php

namespace Tests\Feature;

use App\Contracts\KycVerificationProvider;
use App\Models\User;
use App\Services\KycVerificationProvider\SurepassKycVerificationProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SurepassDigilockerKycApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'kyc.provider' => 'surepass',
            'kyc.surepass.base_url' => 'https://kyc-api.surepass.app',
            'kyc.surepass.token' => 'test-token',
            'kyc.surepass.digilocker_initialize_path' => '/api/v1/digilocker/initialize',
            'kyc.surepass.digilocker_status_path' => '/api/v1/digilocker/status',
            'kyc.surepass.digilocker_download_aadhaar_path' => '/api/v1/digilocker/download-aadhaar',
        ]);

        $this->app->bind(KycVerificationProvider::class, SurepassKycVerificationProvider::class);
    }

    public function test_digilocker_initialize_returns_session_and_stores_client_id(): void
    {
        Http::fake([
            'kyc-api.surepass.app/api/v1/digilocker/initialize' => Http::response([
                'success' => true,
                'status_code' => 200,
                'message' => 'Success',
                'message_code' => 'success',
                'data' => [
                    'client_id' => 'digilocker_test123',
                    'token' => 'test-token-value',
                    'url' => 'https://digilocker-sdk.notbot.in/?token=test-token-value',
                    'expiry_seconds' => 1800,
                ],
            ], 200),
        ]);

        $user = User::factory()->create(['kyc_status' => 'pending']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/digilocker/initialize', [
            'data' => [
                'signup_flow' => true,
                'auth_type' => 'app',
                'voice_assistant_lang' => 'hi',
                'voice_assistant' => true,
                'retry_count' => 3,
                'skip_main_screen' => false,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.client_id', 'digilocker_test123')
            ->assertJsonPath('data.token', 'test-token-value')
            ->assertJsonPath('data.url', 'https://digilocker-sdk.notbot.in/?token=test-token-value')
            ->assertJsonPath('data.kyc.steps.1.status', 'submitted');

        $this->assertDatabaseHas('kyc_details', [
            'user_id' => $user->id,
            'digilocker_client_id' => 'digilocker_test123',
            'aadhaar_verification_status' => 'submitted',
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://kyc-api.surepass.app/api/v1/digilocker/initialize'
                && ($request->data()['data']['auth_type'] ?? null) === 'app';
        });
    }

    public function test_digilocker_status_returns_in_progress_when_not_completed(): void
    {
        Http::fake([
            'kyc-api.surepass.app/api/v1/digilocker/status/*' => Http::response([
                'success' => true,
                'status_code' => 200,
                'message' => 'Success',
                'message_code' => 'success',
                'data' => [
                    'error_description' => null,
                    'status' => 'client_initiated',
                    'completed' => false,
                    'failed' => false,
                    'error_count' => 0,
                    'aadhaar_linked' => false,
                ],
            ], 200),
        ]);

        $user = User::factory()->create(['kyc_status' => 'pending']);
        $user->kycDetail()->create([
            'full_name' => 'Test User',
            'digilocker_client_id' => 'digilocker_test123',
            'aadhaar_verification_status' => 'submitted',
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/digilocker/status/digilocker_test123')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.verified', false)
            ->assertJsonPath('data.completed', false)
            ->assertJsonPath('data.failed', false)
            ->assertJsonPath('data.status', 'client_initiated')
            ->assertJsonPath('data.aadhaar_linked', false);

        $this->assertDatabaseHas('kyc_details', [
            'user_id' => $user->id,
            'aadhaar_verification_status' => 'submitted',
        ]);
    }

    public function test_digilocker_status_verifies_aadhaar_when_completed(): void
    {
        Http::fake([
            'kyc-api.surepass.app/api/v1/digilocker/status/*' => Http::response([
                'success' => true,
                'status_code' => 200,
                'message' => 'Success',
                'data' => [
                    'status' => 'completed',
                    'completed' => true,
                    'failed' => false,
                    'error_count' => 0,
                    'aadhaar_linked' => true,
                ],
            ], 200),
            'kyc-api.surepass.app/api/v1/digilocker/download-aadhaar/*' => Http::response([
                'success' => true,
                'status_code' => 200,
                'message' => 'Success',
                'data' => [
                    'aadhaar_number' => '123456789012',
                    'name' => 'RAJ KUMAR',
                    'date_of_birth' => '1992-08-15',
                    'gender' => 'M',
                    'address' => [
                        'district' => 'Mumbai',
                        'state' => 'Maharashtra',
                        'pincode' => '400001',
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create(['kyc_status' => 'pending']);
        $user->kycDetail()->create([
            'full_name' => 'Test User',
            'digilocker_client_id' => 'digilocker_test123',
            'aadhaar_verification_status' => 'submitted',
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/digilocker/status/digilocker_test123')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.verified', true)
            ->assertJsonPath('data.completed', true)
            ->assertJsonPath('data.aadhaar_number_masked', 'XXXX XXXX 9012')
            ->assertJsonPath('data.kyc.steps.1.status', 'verified');

        $this->assertDatabaseHas('kyc_details', [
            'user_id' => $user->id,
            'digilocker_client_id' => 'digilocker_test123',
            'aadhaar_number' => '123456789012',
            'aadhaar_verification_status' => 'verified',
            'full_name' => 'RAJ KUMAR',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'pincode' => '400001',
        ]);

        $this->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.user.aadhaar.verified', true)
            ->assertJsonPath('data.user.aadhaar.aadhaar_number', 'XXXX XXXX 9012')
            ->assertJsonPath('data.user.aadhaar.aadhaar_number_masked', 'XXXX XXXX 9012');
    }

    public function test_digilocker_status_persists_masked_aadhaar_on_profile(): void
    {
        Http::fake([
            'kyc-api.surepass.app/api/v1/digilocker/status/*' => Http::response([
                'success' => true,
                'status_code' => 200,
                'message' => 'Success',
                'data' => [
                    'status' => 'completed',
                    'completed' => true,
                    'failed' => false,
                    'aadhaar_linked' => true,
                ],
            ], 200),
            'kyc-api.surepass.app/api/v1/digilocker/download-aadhaar/*' => Http::response([
                'success' => true,
                'status_code' => 200,
                'message' => 'Aadhaar downloaded successfully',
                'data' => [
                    'aadhaar_number' => 'XXXX-XXXX-9012',
                    'name' => 'RAJ KUMAR',
                    'date_of_birth' => '01-01-1990',
                    'gender' => 'M',
                    'aadhaar_xml_data' => [
                        'full_name' => 'RAJ KUMAR',
                        'masked_aadhaar' => 'XXXXXXXX9012',
                        'dob' => '01-01-1990',
                        'gender' => 'M',
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create([
            'kyc_status' => 'pending',
            'date_of_birth' => null,
            'gender' => null,
        ]);
        $user->kycDetail()->create([
            'full_name' => 'Test User',
            'digilocker_client_id' => 'digilocker_masked',
            'aadhaar_verification_status' => 'submitted',
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/digilocker/status/digilocker_masked')
            ->assertOk()
            ->assertJsonPath('data.verified', true)
            ->assertJsonPath('data.aadhaar_number_masked', 'XXXX XXXX 9012');

        $this->assertDatabaseHas('kyc_details', [
            'user_id' => $user->id,
            'aadhaar_number' => 'XXXXXXXX9012',
            'aadhaar_verification_status' => 'verified',
            'full_name' => 'RAJ KUMAR',
        ]);

        $this->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.user.aadhaar.verified', true)
            ->assertJsonPath('data.user.aadhaar.aadhaar_number', 'XXXX XXXX 9012')
            ->assertJsonPath('data.user.aadhaar.aadhaar_number_masked', 'XXXX XXXX 9012');
    }

    public function test_digilocker_status_rejects_foreign_client_id(): void
    {
        $user = User::factory()->create(['kyc_status' => 'pending']);
        $user->kycDetail()->create([
            'full_name' => 'Test User',
            'digilocker_client_id' => 'digilocker_mine',
            'aadhaar_verification_status' => 'submitted',
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/digilocker/status/digilocker_other')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.errors.client_id.0', 'This DigiLocker session does not belong to your account.');
    }
}
