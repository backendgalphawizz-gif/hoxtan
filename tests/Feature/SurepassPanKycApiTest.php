<?php

namespace Tests\Feature;

use App\Contracts\KycVerificationProvider;
use App\Models\User;
use App\Services\KycVerificationProvider\SurepassKycVerificationProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SurepassPanKycApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'kyc.provider' => 'surepass',
            'kyc.surepass.base_url' => 'https://kyc-api.surepass.app',
            'kyc.surepass.token' => 'test-token',
            'kyc.surepass.pan_path' => '/api/v1/pan/pan-comprehensive',
            'kyc.surepass.pan_id_field' => 'id_number',
        ]);

        $this->app->bind(KycVerificationProvider::class, SurepassKycVerificationProvider::class);
    }

    public function test_pan_request_otp_verifies_via_surepass_without_otp(): void
    {
        Http::fake([
            'kyc-api.surepass.app/api/v1/pan/pan-comprehensive' => Http::response([
                'success' => true,
                'status_code' => 200,
                'message' => null,
                'message_code' => 'success',
                'data' => [
                    'client_id' => 'pan_abc123',
                    'pan_number' => 'ABCDE1234F',
                    'full_name' => 'ALEXANDER VANCE',
                    'aadhaar_linked' => true,
                    'category' => 'person',
                    'dob' => '1990-04-12',
                ],
            ], 200),
        ]);

        $user = User::factory()->create([
            'name' => 'Alexander Vance',
            'phone' => '9876543210',
            'kyc_status' => 'pending',
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/kyc/pan/request-otp', [
            'pan_number' => 'ABCDE1234F',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.verified', true)
            ->assertJsonPath('data.otp_required', false)
            ->assertJsonPath('data.kyc.steps.0.status', 'verified')
            ->assertJsonPath('data.pan.full_name', 'ALEXANDER VANCE');

        $this->assertDatabaseHas('kyc_details', [
            'user_id' => $user->id,
            'pan_number' => 'ABCDE1234F',
            'pan_verification_status' => 'verified',
            'full_name' => 'ALEXANDER VANCE',
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://kyc-api.surepass.app/api/v1/pan/pan-comprehensive'
                && $request['id_number'] === 'ABCDE1234F'
                && $request->hasHeader('Authorization', 'Bearer test-token');
        });
    }

    public function test_pan_verification_fails_when_surepass_rejects(): void
    {
        Http::fake([
            'kyc-api.surepass.app/*' => Http::response([
                'success' => false,
                'status_code' => 422,
                'message' => 'Invalid PAN',
                'message_code' => 'invalid_pan',
            ], 422),
        ]);

        $user = User::factory()->create(['kyc_status' => 'pending']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/kyc/pan/request-otp', [
            'pan_number' => 'ABCDE1234F',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.errors.pan_number.0', 'Invalid PAN');
    }
}
