<?php

namespace Tests\Feature;

use App\Models\BlockedPincode;
use App\Models\User;
use App\Services\BlockedPincodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BlockedPincodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_detects_blocked_pincode(): void
    {
        BlockedPincode::query()->create([
            'pincode' => '110001',
            'is_active' => true,
        ]);

        $service = app(BlockedPincodeService::class);

        $this->assertTrue($service->isBlocked('110001'));
        $this->assertFalse($service->isBlocked('560001'));
    }

    public function test_bulk_import_skips_duplicates_and_invalid_rows(): void
    {
        BlockedPincode::query()->create([
            'pincode' => '110001',
            'is_active' => true,
        ]);

        $service = app(BlockedPincodeService::class);

        $result = $service->importFromText("pincode,city,state,reason\n110001\n560001,Bangalore,Karnataka,No service\n12abc\n");

        $this->assertSame(1, $result['imported']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(1, $result['invalid']);
        $this->assertDatabaseHas('blocked_pincodes', ['pincode' => '560001', 'city' => 'Bangalore']);
    }

    public function test_user_cannot_save_address_with_blocked_pincode(): void
    {
        BlockedPincode::query()->create([
            'pincode' => '560066',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'phone' => '9876543210',
            'mpin' => '1234',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/addresses', [
            'address_type' => 'home',
            'is_default' => true,
            'full_name' => 'Alexander Vance',
            'address_line' => '5th Floor, Tech Park, Whitefield Main Road',
            'city' => 'Bangalore',
            'state' => 'Karnataka',
            'pincode' => '560066',
            'phone' => '9876543210',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['pincode']);
    }
}
