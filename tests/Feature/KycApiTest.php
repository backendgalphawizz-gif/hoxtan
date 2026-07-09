<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KycApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_complete_kyc_flow_with_stub_provider(): void
    {
        Storage::fake('public');
        config(['otp.expose_in_response' => true]);

        $user = User::factory()->create([
            'name' => 'Alexander Vance',
            'phone' => '9876543210',
            'mpin' => '1234',
            'kyc_status' => 'pending',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/kyc/config')
            ->assertOk()
            ->assertJsonPath('data.title', 'Identity Vault')
            ->assertJsonStructure(['data' => ['steps', 'face_requirements']]);

        $this->getJson('/api/v1/kyc')
            ->assertOk()
            ->assertJsonPath('data.kyc.progress_percent', 0)
            ->assertJsonPath('data.kyc.steps.0.key', 'pan');

        $panOtp = $this->postJson('/api/v1/kyc/pan/request-otp', [
            'pan_number' => 'ABCDE1234F',
        ])->assertOk()->json('data.otp');

        $this->postJson('/api/v1/kyc/pan/verify-otp', [
            'pan_number' => 'ABCDE1234F',
            'otp' => $panOtp,
        ])->assertOk()
            ->assertJsonPath('data.kyc.steps.0.status', 'verified');

        $aadhaarOtp = $this->postJson('/api/v1/kyc/aadhaar/request-otp', [
            'aadhaar_number' => '123456789012',
        ])->assertOk()->json('data.otp');

        $this->postJson('/api/v1/kyc/aadhaar/verify-otp', [
            'aadhaar_number' => '123456789012',
            'otp' => $aadhaarOtp,
        ])->assertOk()
            ->assertJsonPath('data.kyc.steps.1.status', 'verified');

        $this->post('/api/v1/kyc/face', [
            'selfie' => UploadedFile::fake()->image('selfie.jpg'),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonStructure(['data' => ['face_photo_url', 'kyc']]);

        $this->postJson('/api/v1/kyc/bank', [
            'account_holder_name' => 'Alexander Vance',
            'bank_name' => 'HDFC Bank',
            'account_number' => '123456789012',
            'ifsc_code' => 'HDFC0001234',
            'upi_id' => 'alex@upi',
        ])->assertOk()
            ->assertJsonPath('data.kyc.can_submit', true);

        $user->refresh();
        $this->assertSame('submitted', $user->kyc_status);
    }
}
