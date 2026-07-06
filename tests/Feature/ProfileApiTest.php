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
            ->assertJsonPath('data.user.market_alerts', true);
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
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.name', 'Alexander Vance')
            ->assertJsonPath('data.user.nominee.name', 'Jane Vance');
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
            ->assertJsonPath('success', true);
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }
}
