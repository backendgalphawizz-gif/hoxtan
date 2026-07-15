<?php

namespace Tests\Feature;

use App\Models\JewelleryProduct;
use App\Models\JewelleryProductVariant;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JewelleryCheckoutVariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_buy_now_requires_variant_id_for_sized_product(): void
    {
        [$user, $address, $product] = $this->sizedProductFixture();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/jewellery/checkout/buy-now', [
            'product_id' => $product->id,
            'quantity' => 1,
            'address_id' => $address->id,
            'payment_type' => 'full',
        ])->assertUnprocessable()
            ->assertJsonPath('data.errors.variant_id.0', 'Please select a size variant for this product.');
    }

    public function test_buy_now_with_variant_uses_variant_weight_and_stores_variant(): void
    {
        [$user, $address, $product, $small, $large] = $this->sizedProductFixture();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/jewellery/checkout/buy-now', [
            'product_id' => $product->id,
            'variant_id' => $large->id,
            'quantity' => 1,
            'address_id' => $address->id,
            'payment_type' => 'full',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.variant_id', $large->id)
            ->assertJsonPath('data.product.variant_id', $large->id)
            ->assertJsonPath('data.product.size', '18')
            ->assertJsonPath('data.price_breakup.unit_weight_grams', 12)
            ->assertJsonPath('data.order.items.0.variant_id', $large->id)
            ->assertJsonPath('data.order.items.0.size', '18');

        $this->assertDatabaseHas('jewellery_order_items', [
            'jewellery_product_id' => $product->id,
            'jewellery_product_variant_id' => $large->id,
            'size' => '18',
            'weight_grams' => 12.000,
        ]);

        $this->assertSame(12.0, (float) $response->json('data.price_breakup.unit_weight_grams'));
        $this->assertNotSame($small->id, (int) $response->json('data.variant_id'));
    }

    public function test_checkout_summary_accepts_variant_id(): void
    {
        [$user, $address, $product, $small, $large] = $this->sizedProductFixture();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/jewellery/checkout/summary?'.http_build_query([
            'product_id' => $product->id,
            'variant_id' => $large->id,
            'quantity' => 1,
            'address_id' => $address->id,
        ]))
            ->assertOk()
            ->assertJsonPath('data.has_size_variants', true)
            ->assertJsonPath('data.variant_id', $large->id)
            ->assertJsonPath('data.selected_variant.id', $large->id)
            ->assertJsonPath('data.selected_variant.size', '18')
            ->assertJsonPath('data.product.size', '18')
            ->assertJsonPath('data.price_breakup.unit_weight_grams', 12)
            ->assertJsonPath('data.variants.0.id', $small->id)
            ->assertJsonPath('data.variants.1.id', $large->id)
            ->assertJsonCount(2, 'data.variants')
            ->assertJsonCount(2, 'data.product.variants');
    }

    public function test_checkout_summary_lists_variants_without_variant_id(): void
    {
        [$user, $address, $product, $small, $large] = $this->sizedProductFixture();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/jewellery/checkout/summary?'.http_build_query([
            'product_id' => $product->id,
            'quantity' => 1,
            'address_id' => $address->id,
        ]))
            ->assertOk()
            ->assertJsonPath('data.has_size_variants', true)
            ->assertJsonPath('data.variants.0.size', '16')
            ->assertJsonPath('data.variants.1.size', '18')
            ->assertJsonCount(2, 'data.variants')
            ->assertJsonPath('data.variant_id', $small->id);
    }

    /**
     * @return array{0: User, 1: UserAddress, 2: JewelleryProduct, 3: JewelleryProductVariant, 4: JewelleryProductVariant}
     */
    protected function sizedProductFixture(): array
    {
        $user = User::factory()->create(['phone' => '9876500101', 'mpin' => '1234']);

        $address = UserAddress::query()->create([
            'user_id' => $user->id,
            'address_type' => 'home',
            'is_default' => true,
            'full_name' => 'Variant User',
            'address_line' => 'MG Road',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'pincode' => '400001',
            'phone' => '9876500101',
        ]);

        $product = JewelleryProduct::query()->create([
            'name' => 'Sized Ring',
            'metal_type' => 'gold',
            'purity' => '22K',
            'weight_grams' => 8,
            'price' => 0,
            'has_size_variants' => true,
            'stock_status' => 'in_stock',
            'is_active' => true,
        ]);

        $small = JewelleryProductVariant::query()->create([
            'jewellery_product_id' => $product->id,
            'size' => '16',
            'weight_grams' => 8,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $large = JewelleryProductVariant::query()->create([
            'jewellery_product_id' => $product->id,
            'size' => '18',
            'weight_grams' => 12,
            'sort_order' => 2,
            'is_active' => true,
        ]);

        return [$user, $address, $product->fresh('variants'), $small->fresh(), $large->fresh()];
    }
}
