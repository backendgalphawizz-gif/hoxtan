<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\JewelleryOrder;
use App\Models\JewelleryOrderItem;
use App\Models\JewelleryProduct;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DriverDeliveryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_delivery_config_returns_failure_reasons_and_statuses(): void
    {
        $token = $this->driverAuthToken(['phone' => '9876543500']);

        $this->getJson('/api/v1/driver/deliveries/config', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('data.otp_length', 4)
            ->assertJsonStructure([
                'data' => [
                    'otp_length',
                    'failure_reasons' => [['value', 'label']],
                    'statuses' => [
                        'accepted' => ['label', 'color'],
                        'picked_up' => ['label', 'color'],
                        'delivered' => ['label', 'color'],
                        'cancelled' => ['label', 'color'],
                    ],
                ],
            ]);
    }

    public function test_driver_can_view_delivery_detail_with_actions(): void
    {
        $token = $this->driverAuthToken(['phone' => '9876543501']);
        $driver = Driver::query()->where('phone', '9876543501')->firstOrFail();
        $user = User::factory()->create(['name' => 'Alexander Vance', 'phone' => '9844240100']);

        $payment = Payment::query()->create([
            'reference_id' => 'PAY-10001',
            'user_id' => $user->id,
            'amount' => 96235,
            'currency' => 'INR',
            'status' => 'completed',
            'gateway' => 'bank_transfer',
            'paid_at' => now(),
        ]);

        $product = JewelleryProduct::query()->create([
            'name' => 'Antique Gold Necklace',
            'metal_type' => 'gold',
            'purity' => '22K',
            'weight_grams' => 26,
            'price' => 96235,
            'stock_status' => 'in_stock',
            'is_active' => true,
        ]);

        $order = JewelleryOrder::query()->create([
            'order_number' => 'PR-76451236',
            'user_id' => $user->id,
            'driver_id' => $driver->id,
            'payment_id' => $payment->id,
            'subtotal' => 103338,
            'metal_value' => 92235.80,
            'making_charge_amount' => 1500,
            'gst_percent' => 3,
            'gst_amount' => 2500.20,
            'discount_amount' => 7102.20,
            'total_amount' => 96235.80,
            'status' => 'processing',
            'shipping_name' => 'Alexander Vance',
            'shipping_phone' => '9844240100',
            'shipping_address' => '5th 134, Greenfield Street, Ave, Manchester',
            'delivery_otp' => '1234',
        ]);

        JewelleryOrderItem::query()->create([
            'jewellery_order_id' => $order->id,
            'jewellery_product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 96235.80,
            'line_total' => 96235.80,
        ]);

        $this->getJson('/api/v1/driver/tasks/deliveries/'.$order->id, [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('data.task.driver_delivery_status', 'accepted')
            ->assertJsonPath('data.delivery.order_number', 'PR-76451236')
            ->assertJsonPath('data.delivery.driver_delivery_status', 'accepted')
            ->assertJsonPath('data.delivery.customer.name', 'Alexander Vance')
            ->assertJsonPath('data.delivery.customer.phone_display', '+91 9844240100')
            ->assertJsonPath('data.delivery.product.title', 'Antique Gold Necklace')
            ->assertJsonPath('data.delivery.payment.method_label', 'Paid by Bank Transfer')
            ->assertJsonCount(2, 'data.delivery.available_actions')
            ->assertJsonMissingPath('data.delivery.delivery_otp');
    }

    public function test_driver_can_mark_order_as_picked_up(): void
    {
        $token = $this->driverAuthToken(['phone' => '9876543502']);
        $driver = Driver::query()->where('phone', '9876543502')->firstOrFail();
        $user = User::factory()->create();
        $order = $this->createAssignedOrder($driver, $user);

        $this->postJson('/api/v1/driver/tasks/deliveries/'.$order->id.'/picked-up', [], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('data.delivery.driver_delivery_status', 'picked_up')
            ->assertJsonPath('message', 'Order marked as picked up.');

        $order->refresh();
        $this->assertNotNull($order->picked_up_at);
    }

    public function test_driver_can_verify_delivery_with_otp_and_proof_image(): void
    {
        Storage::fake('public');

        $token = $this->driverAuthToken(['phone' => '9876543503']);
        $driver = Driver::query()->where('phone', '9876543503')->firstOrFail();
        $user = User::factory()->create();
        $order = $this->createAssignedOrder($driver, $user, [
            'picked_up_at' => now(),
            'delivery_otp' => '5678',
        ]);

        $this->postJson('/api/v1/driver/tasks/deliveries/'.$order->id.'/verify-delivery', [
            'otp' => '5678',
            'proof_image' => UploadedFile::fake()->image('delivery-proof.jpg'),
        ], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('data.delivery.driver_delivery_status', 'delivered')
            ->assertJsonPath('message', 'Order delivered successfully.');

        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertNotNull($order->delivered_at);
        $this->assertNotNull($order->delivery_proof_image);
        Storage::disk('public')->assertExists($order->delivery_proof_image);
    }

    public function test_verify_delivery_rejects_invalid_otp(): void
    {
        $token = $this->driverAuthToken(['phone' => '9876543504']);
        $driver = Driver::query()->where('phone', '9876543504')->firstOrFail();
        $user = User::factory()->create();
        $order = $this->createAssignedOrder($driver, $user, [
            'picked_up_at' => now(),
            'delivery_otp' => '1111',
        ]);

        $this->postJson('/api/v1/driver/tasks/deliveries/'.$order->id.'/verify-delivery', [
            'otp' => '9999',
            'proof_image' => UploadedFile::fake()->image('delivery-proof.jpg'),
        ], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['otp']);
    }

    public function test_driver_can_mark_delivery_as_unable_to_deliver(): void
    {
        $token = $this->driverAuthToken(['phone' => '9876543505']);
        $driver = Driver::query()->where('phone', '9876543505')->firstOrFail();
        $user = User::factory()->create();
        $order = $this->createAssignedOrder($driver, $user);

        $this->postJson('/api/v1/driver/tasks/deliveries/'.$order->id.'/unable-to-deliver', [
            'reason' => 'customer_unavailable',
        ], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('data.delivery.driver_delivery_status', 'cancelled')
            ->assertJsonPath('data.delivery.delivery_failure_reason', 'customer_unavailable')
            ->assertJsonPath('message', 'Delivery marked as undeliverable.');

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
    }

    public function test_driver_cannot_update_delivery_assigned_to_another_driver(): void
    {
        $token = $this->driverAuthToken(['phone' => '9876543506']);
        $otherDriver = Driver::query()->create([
            'name' => 'Other Driver',
            'phone' => '9876543598',
            'vehicle_type' => 'bike',
            'is_active' => true,
        ]);
        $user = User::factory()->create();
        $order = $this->createAssignedOrder($otherDriver, $user);

        $this->postJson('/api/v1/driver/tasks/deliveries/'.$order->id.'/picked-up', [], [
            'Authorization' => 'Bearer '.$token,
        ])->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createAssignedOrder(Driver $driver, User $user, array $attributes = []): JewelleryOrder
    {
        return JewelleryOrder::query()->create(array_merge([
            'order_number' => 'PR-'.fake()->unique()->numerify('########'),
            'user_id' => $user->id,
            'driver_id' => $driver->id,
            'subtotal' => 1000,
            'total_amount' => 1000,
            'status' => 'processing',
            'shipping_address' => 'Test address',
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function driverAuthToken(array $attributes = []): string
    {
        config(['otp.expose_in_response' => true]);

        $driver = Driver::query()->create(array_merge([
            'name' => 'Test Driver',
            'phone' => '9876500000',
            'vehicle_type' => 'bike',
            'is_active' => true,
        ], $attributes));

        $send = $this->postJson('/api/v1/driver/login/send-otp', [
            'phone' => $driver->phone,
        ]);

        $verify = $this->postJson('/api/v1/driver/login/verify-otp', [
            'phone' => $driver->phone,
            'otp' => $send->json('data.otp'),
        ]);

        return (string) $verify->json('data.token');
    }
}
