<?php

namespace Tests\Feature;

use App\Contracts\KycVerificationProvider;
use App\Models\User;
use App\Services\KycVerificationProvider\SurepassKycVerificationProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SurepassBankKycApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'kyc.provider' => 'surepass',
            'kyc.surepass.base_url' => 'https://kyc-api.surepass.app',
            'kyc.surepass.token' => 'test-token',
            'kyc.surepass.bank_path' => '/api/v1/bank-verification/',
            'kyc.surepass.bank_account_field' => 'id_number',
            'kyc.surepass.bank_ifsc_field' => 'ifsc',
            'kyc.surepass.bank_ifsc_details' => true,
        ]);

        $this->app->bind(KycVerificationProvider::class, SurepassKycVerificationProvider::class);
    }

    public function test_bank_verify_via_surepass_marks_verified_when_name_matches(): void
    {
        Http::fake([
            'kyc-api.surepass.app/*' => Http::response([
                'success' => true,
                'status_code' => 200,
                'message' => 'Bank account verified successfully.',
                'message_code' => 'success',
                'data' => [
                    'client_id' => 'bank_verification_abc123',
                    'account_exists' => true,
                    'full_name' => 'GOUTAM PATIDAR',
                    'upi_id' => null,
                    'remarks' => 'Transaction Successful',
                    'ifsc_details' => [
                        'bank' => 'HDFC Bank',
                        'ifsc' => 'HDFC0001234',
                        'branch' => 'Indore',
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create([
            'name' => 'Goutam Patidar',
            'kyc_status' => 'pending',
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/kyc/bank/verify', [
            'account_holder_name' => 'Goutam Patidar',
            'bank_name' => 'HDFC Bank',
            'account_number' => '123456789012',
            'ifsc_code' => 'HDFC0001234',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.verified', true)
            ->assertJsonPath('data.name_matched', true)
            ->assertJsonPath('data.bank.verification_status', 'verified')
            ->assertJsonPath('data.bank.account_holder_name', 'GOUTAM PATIDAR')
            ->assertJsonPath('data.kyc.steps.3.status', 'verified');

        $this->assertDatabaseHas('kyc_details', [
            'user_id' => $user->id,
            'account_number' => '123456789012',
            'ifsc_code' => 'HDFC0001234',
            'bank_verification_status' => 'verified',
            'account_holder_name' => 'GOUTAM PATIDAR',
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/v1/bank-verification')
                && $request['id_number'] === '123456789012'
                && $request['ifsc'] === 'HDFC0001234'
                && $request['ifsc_details'] === true
                && $request->hasHeader('Authorization', 'Bearer test-token');
        });
    }

    public function test_bank_verify_succeeds_when_account_holder_matches_even_if_profile_name_differs(): void
    {
        Http::fake([
            'kyc-api.surepass.app/*' => Http::response([
                'success' => true,
                'status_code' => 200,
                'data' => [
                    'client_id' => 'bank_verification_rahul',
                    'account_exists' => true,
                    'full_name' => 'RAHUL JOSHI',
                    'remarks' => 'Transaction Successful',
                ],
            ], 200),
        ]);

        $user = User::factory()->create([
            'name' => 'Different Profile Name',
            'kyc_status' => 'pending',
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/kyc/bank/verify', [
            'account_holder_name' => 'Rahul joshi',
            'bank_name' => 'Bank of Baroda',
            'account_number' => '05050100009895',
            'ifsc_code' => 'BARB0UJJAIN',
        ])
            ->assertOk()
            ->assertJsonPath('data.verified', true)
            ->assertJsonPath('data.name_matched', true)
            ->assertJsonPath('data.bank.account_holder_name', 'RAHUL JOSHI');
    }

    public function test_bank_verify_fails_when_name_does_not_match(): void
    {
        Http::fake([
            'kyc-api.surepass.app/*' => Http::response([
                'success' => true,
                'status_code' => 200,
                'data' => [
                    'client_id' => 'bank_verification_mismatch',
                    'account_exists' => true,
                    'full_name' => 'GOUTAM PATIDAR',
                    'remarks' => 'Transaction Successful',
                ],
            ], 200),
        ]);

        $user = User::factory()->create([
            'name' => 'Goutam Patidar',
            'kyc_status' => 'pending',
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/kyc/bank/verify', [
            'account_holder_name' => 'Wrong Name',
            'bank_name' => 'HDFC Bank',
            'account_number' => '123456789012',
            'ifsc_code' => 'HDFC0001234',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath(
                'data.errors.account_holder_name.0',
                'Account holder name does not match the name registered with the bank.',
            );

        $this->assertDatabaseMissing('kyc_details', [
            'user_id' => $user->id,
            'bank_verification_status' => 'verified',
        ]);
    }

    public function test_bank_verify_fails_when_account_does_not_exist(): void
    {
        Http::fake([
            'kyc-api.surepass.app/*' => Http::response([
                'success' => true,
                'status_code' => 200,
                'data' => [
                    'client_id' => 'bank_verification_missing',
                    'account_exists' => false,
                    'full_name' => null,
                    'remarks' => 'Invalid Account',
                ],
            ], 200),
        ]);

        $user = User::factory()->create([
            'name' => 'Goutam Patidar',
            'kyc_status' => 'pending',
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/kyc/bank', [
            'account_holder_name' => 'Goutam Patidar',
            'bank_name' => 'HDFC Bank',
            'account_number' => '000000000000',
            'ifsc_code' => 'HDFC0001234',
        ])
            ->assertStatus(422)
            ->assertJsonPath('data.errors.account_number.0', 'Invalid Account');
    }

    public function test_bank_verify_auto_approves_kyc_when_pan_already_verified(): void
    {
        Http::fake([
            'kyc-api.surepass.app/*' => Http::response([
                'success' => true,
                'status_code' => 200,
                'data' => [
                    'client_id' => 'bank_auto_approve',
                    'account_exists' => true,
                    'full_name' => 'RAHUL JOSHI',
                    'remarks' => 'Transaction Successful',
                ],
            ], 200),
        ]);

        $user = User::factory()->create([
            'name' => 'Rahul joshi',
            'kyc_status' => 'pending',
        ]);
        $user->kycDetail()->create([
            'full_name' => 'RAHUL JOSHI',
            'pan_number' => 'ABCDE1234F',
            'pan_verification_status' => 'verified',
            'pan_verified_at' => now(),
            'aadhaar_verification_status' => 'action_required',
            'face_verification_status' => 'pending',
            'bank_verification_status' => 'pending',
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/kyc/bank/verify', [
            'account_holder_name' => 'Rahul joshi',
            'bank_name' => 'Bank of Baroda',
            'account_number' => '05050100009895',
            'ifsc_code' => 'BARB0UJJAIN',
        ])
            ->assertOk()
            ->assertJsonPath('data.verified', true);

        $user->refresh();
        $this->assertSame('approved', $user->kyc_status);
        $this->assertSame('verified', $user->kycDetail->bank_verification_status);
        $this->assertNotNull($user->kycDetail->reviewed_at);
    }
}
