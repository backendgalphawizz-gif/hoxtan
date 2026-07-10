<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AddressApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_manage_addresses(): void
    {
        $user = User::factory()->create([
            'phone' => '9876543210',
            'mpin' => '1234',
        ]);

        Sanctum::actingAs($user);

        $create = $this->postJson('/api/v1/addresses', [
            'address_type' => 'home',
            'is_default' => true,
            'full_name' => 'Alexander Vance',
            'flat_no' => 'A-501',
            'address_line' => '5th Floor, Tech Park, Whitefield Main Road',
            'city' => 'Bangalore',
            'state' => 'Karnataka',
            'pincode' => '560066',
            'phone' => '9876543210',
        ]);

        $create->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.address.is_default', true)
            ->assertJsonPath('data.address.address_type_label', 'HOME')
            ->assertJsonPath('data.address.flat_no', 'A-501');

        $addressId = $create->json('data.address.id');

        $list = $this->getJson('/api/v1/addresses');

        $list->assertOk()
            ->assertJsonPath('data.addresses.0.id', $addressId)
            ->assertJsonCount(1, 'data.addresses');

        $update = $this->putJson("/api/v1/addresses/{$addressId}", [
            'address_type' => 'other',
            'is_default' => true,
            'full_name' => 'Rahul Sharma',
            'flat_no' => 'B-12',
            'address_line' => '45, Green Park Society',
            'city' => 'Bangalore',
            'state' => 'Karnataka',
            'pincode' => '560066',
            'phone' => '9876543211',
        ]);

        $update->assertOk()
            ->assertJsonPath('data.address.full_name', 'Rahul Sharma')
            ->assertJsonPath('data.address.flat_no', 'B-12')
            ->assertJsonPath('data.address.address_type_label', 'OTHER');

        $second = $this->postJson('/api/v1/addresses', [
            'address_type' => 'work',
            'full_name' => 'Alexander Vance',
            'address_line' => 'Office Block A',
            'city' => 'Bangalore',
            'state' => 'Karnataka',
            'pincode' => '560001',
            'phone' => '9876543210',
        ]);

        $secondId = $second->json('data.address.id');

        $setDefault = $this->postJson("/api/v1/addresses/{$secondId}/default");

        $setDefault->assertOk()
            ->assertJsonPath('data.address.is_default', true);

        $this->assertDatabaseHas('user_addresses', [
            'id' => $secondId,
            'is_default' => true,
        ]);

        $this->assertDatabaseHas('user_addresses', [
            'id' => $addressId,
            'is_default' => false,
        ]);

        $delete = $this->deleteJson("/api/v1/addresses/{$addressId}");

        $delete->assertOk()
            ->assertJsonPath('message', 'Address deleted successfully.');

        $this->assertDatabaseMissing('user_addresses', ['id' => $addressId]);
    }

    public function test_user_cannot_access_another_users_address(): void
    {
        $owner = User::factory()->create(['phone' => '9876543210', 'mpin' => '1234']);
        $other = User::factory()->create(['phone' => '9876543211', 'mpin' => '1234']);

        $address = UserAddress::query()->create([
            'user_id' => $owner->id,
            'address_type' => 'home',
            'is_default' => true,
            'full_name' => 'Owner User',
            'address_line' => 'Test Street',
            'city' => 'Bangalore',
            'state' => 'Karnataka',
            'pincode' => '560001',
            'phone' => '9876543210',
        ]);

        Sanctum::actingAs($other);

        $this->getJson("/api/v1/addresses/{$address->id}")
            ->assertNotFound();
    }
}
