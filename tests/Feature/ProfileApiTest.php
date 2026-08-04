<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_profile_returns_extended_fields(): void
    {
        $user = User::factory()->create([
            'phone' => '9876543210',
            'mpin' => '1234',
            'email' => 'alex@example.com',
            'primary_residence' => 'London, Mayfair',
            'gender' => 'male',
            'market_alerts' => true,
        ]);

        $user->kycDetail()->create([
            'full_name' => 'GOUTAM PATIDAR',
            'pan_number' => 'HLCPP0624P',
            'pan_verification_status' => 'verified',
            'pan_verified_at' => now(),
            'date_of_birth' => '2005-01-25',
            'aadhaar_verification_status' => 'action_required',
            'face_verification_status' => 'pending',
            'bank_verification_status' => 'pending',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/profile');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.phone', '9876543210')
            ->assertJsonPath('data.user.mpin', '1234')
            ->assertJsonPath('data.user.has_mpin', true)
            ->assertJsonPath('data.user.email', 'alex@example.com')
            ->assertJsonPath('data.user.primary_residence', 'London, Mayfair')
            ->assertJsonPath('data.user.gender', 'male')
            ->assertJsonPath('data.user.market_alerts', true)
            ->assertJsonPath('data.user.wallet_balance', 0)
            ->assertJsonPath('data.user.gold_holdings', 0)
            ->assertJsonPath('data.user.silver_holdings', 0)
            ->assertJsonPath('data.user.mpin_length', 4)
            ->assertJsonPath('data.user.pan.full_name', 'GOUTAM PATIDAR')
            ->assertJsonPath('data.user.pan.pan_number_masked', 'HLXXXXX24P')
            ->assertJsonPath('data.user.pan.dob', '2005-01-25')
            ->assertJsonPath('data.user.pan.verified', true)
            ->assertJsonPath('data.user.pan.verification_status', 'verified')
            ->assertJsonPath('data.user.aadhaar.verified', false)
            ->assertJsonPath('data.user.aadhaar.verification_status', 'action_required');

        $userPayload = $response->json('data.user');
        foreach (['id', 'mpin_length', 'wallet_balance', 'gold_holdings', 'silver_holdings'] as $numericKey) {
            $this->assertIsNumeric($userPayload[$numericKey] ?? null, "Expected numeric {$numericKey}");
            $this->assertNotNull($userPayload[$numericKey], "Expected non-null {$numericKey}");
        }
        $this->assertIsString($userPayload['mpin']);
    }

    public function test_update_profile(): void
    {
        $user = User::factory()->create([
            'phone' => '9876543210',
            'mpin' => '1234',
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/profile', [
            'name' => 'Alexander Vance',
            'email' => 'vance.a@hoxtan.com',
            'primary_residence' => 'London, Mayfair',
            'gender' => 'male',
            'date_of_birth' => '1990-04-12',
            'market_alerts' => false,
            'nominee' => [
                'name' => 'Jane Vance',
                'relation' => 'Spouse',
                'phone' => '9876543211',
                'date_of_birth' => '1992-08-20',
            ],
            'account_holder_name' => 'Alexander Vance',
            'bank_name' => 'HDFC Bank',
            'account_number' => '123456789012',
            'ifsc_code' => 'HDFC0001234',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.name', 'Alexander Vance')
            ->assertJsonPath('data.user.date_of_birth', '1990-04-12')
            ->assertJsonPath('data.user.date_of_birth_display', '12/04/1990')
            ->assertJsonPath('data.user.nominee.name', 'Jane Vance')
            ->assertJsonPath('data.user.bank.account_holder_name', 'Alexander Vance')
            ->assertJsonPath('data.user.bank.bank_name', 'HDFC Bank')
            ->assertJsonPath('data.user.bank.account_number', '123456789012')
            ->assertJsonPath('data.user.bank.ifsc_code', 'HDFC0001234');
    }

    public function test_update_profile_date_of_birth_only(): void
    {
        $user = User::factory()->create([
            'phone' => '9876543210',
            'mpin' => '1234',
            'date_of_birth' => null,
        ]);

        Sanctum::actingAs($user);

        $this->putJson('/api/v1/profile', [
            'date_of_birth' => '1998-03-21',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.date_of_birth', '1998-03-21')
            ->assertJsonPath('data.user.date_of_birth_display', '21/03/1998');

        $this->putJson('/api/v1/profile', [
            'dob' => '1997-11-05',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.date_of_birth', '1997-11-05');
    }

    public function test_update_profile_photo_only(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'phone' => '9876543210',
            'mpin' => '1234',
            'name' => 'Alex Vance',
        ]);

        Sanctum::actingAs($user);

        $response = $this->post('/api/v1/profile/photo', [
            'profile_photo' => UploadedFile::fake()->image('avatar.jpg'),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.name', 'Alex Vance')
            ->assertJsonPath('data.user.profile_photo_url', fn ($url) => filled($url));

        $user->refresh();
        $this->assertNotNull($user->profile_photo);
        Storage::disk('public')->assertExists($user->profile_photo);
    }

    public function test_update_profile_photo_via_profile_endpoint_with_image_field(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'phone' => '9876543211',
            'mpin' => '1234',
            'name' => 'Alex Vance',
        ]);

        Sanctum::actingAs($user);

        $response = $this->post('/api/v1/profile', [
            'image' => UploadedFile::fake()->image('avatar.jpg'),
            'account_holder_name' => 'Alex Vance',
            'bank_name' => 'HDFC Bank',
            'account_number' => '123456789012',
            'ifsc_code' => 'HDFC0001234',
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.image', fn ($url) => filled($url))
            ->assertJsonPath('data.user.image_url', fn ($url) => filled($url))
            ->assertJsonPath('data.user.profile_photo_url', fn ($url) => filled($url));

        $user->refresh();
        Storage::disk('public')->assertExists($user->profile_photo);
    }

    public function test_update_profile_photo_via_profile_endpoint_base64(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'phone' => '9876543212',
            'mpin' => '1234',
            'name' => 'Alex Vance',
        ]);

        Sanctum::actingAs($user);

        $image = base64_encode(file_get_contents(UploadedFile::fake()->image('avatar.jpg')->path()));

        $response = $this->putJson('/api/v1/profile', [
            'image' => 'data:image/jpeg;base64,'.$image,
            'account_holder_name' => 'Alex Vance',
            'bank_name' => 'HDFC Bank',
            'account_number' => '123456789012',
            'ifsc_code' => 'HDFC0001234',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.image', fn ($url) => filled($url))
            ->assertJsonPath('data.user.profile_photo_url', fn ($url) => filled($url));

        $user->refresh();
        Storage::disk('public')->assertExists($user->profile_photo);
    }

    public function test_update_mpin(): void
    {
        $user = User::factory()->create([
            'phone' => '9876543210',
            'mpin' => '1234',
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/profile/mpin', [
            'current_mpin' => '1234',
            'mpin' => '5678',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.mpin', '5678');

        $user->refresh();
        $this->assertTrue($user->verifyMpin('5678'));
    }

    public function test_close_account_deletes_user(): void
    {
        $user = User::factory()->create([
            'phone' => '9876543210',
            'mpin' => '1234',
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/v1/profile', [
            'mpin' => '1234',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.closed', true);
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_close_account_via_post_endpoint(): void
    {
        $user = User::factory()->create([
            'phone' => '9876543211',
            'mpin' => '1234',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/profile/close-account', [
            'mpin' => '1234',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.closed', true)
            ->assertJsonPath('message', 'Your account has been closed successfully.');

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_close_account_rejects_invalid_mpin(): void
    {
        $user = User::factory()->create([
            'phone' => '9876543212',
            'mpin' => '1234',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/profile/close', [
            'mpin' => '9999',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.errors.mpin.0', 'Invalid M-PIN. Account could not be closed.');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null]);
    }

    public function test_close_account_rejects_when_active_emi_exists(): void
    {
        $user = User::factory()->create([
            'phone' => '9876543213',
            'mpin' => '1234',
        ]);

        $order = \App\Models\JewelleryOrder::query()->create([
            'order_number' => 'EMI-CLOSE-001',
            'user_id' => $user->id,
            'subtotal' => 10000,
            'total_amount' => 10000,
            'payment_mode' => 'emi',
            'emi_tenure' => 3,
            'total_emi_cost' => 10500,
            'monthly_emi_amount' => 3500,
            'status' => 'pending',
            'shipping_address' => 'Test address',
        ]);

        \App\Models\JewelleryOrderEmiInstallment::query()->create([
            'jewellery_order_id' => $order->id,
            'installment_number' => 1,
            'amount' => 3500,
            'due_date' => now()->toDateString(),
            'status' => 'pending',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/profile/close-account', [
            'mpin' => '1234',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath(
                'data.errors.account.0',
                'Your account cannot be closed while you have an active jewellery EMI. Please complete or cancel the EMI plan first.'
            );

        $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null]);
    }
}
