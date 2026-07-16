<?php

namespace Tests\Feature;

use App\Models\Referral;
use App\Models\User;
use App\Services\AppSettingService;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReferralPurchaseBonusTest extends TestCase
{
    use RefreshDatabase;

    public function test_referral_stays_pending_on_signup_until_purchase_threshold(): void
    {
        app(AppSettingService::class)->setMany([
            'referral_bonus_enabled' => '1',
            'referral_bonus_amount' => '150',
            'referral_purchase_threshold' => '2000',
            'welcome_bonus_enabled' => '0',
        ]);

        $referrer = User::factory()->create([
            'phone' => '9876509001',
            'referral_code' => 'REFCODE1',
            'wallet_balance' => 0,
        ]);

        $referee = app(\App\Services\UserRegistrationService::class)->register(
            'Referred User',
            '9876509002',
            '1234',
            'REFCODE1',
        );

        $referral = Referral::query()->where('referee_id', $referee->id)->first();

        $this->assertNotNull($referral);
        $this->assertSame('pending', $referral->status);
        $this->assertNull($referral->credited_at);

        $referrer->refresh();
        $this->assertEqualsWithDelta(0.0, (float) $referrer->wallet_balance, 0.01);
    }

    public function test_referrer_gets_bonus_when_referee_metal_purchase_crosses_threshold(): void
    {
        app(AppSettingService::class)->setMany([
            'referral_bonus_enabled' => '1',
            'referral_bonus_amount' => '150',
            'referral_purchase_threshold' => '2000',
            'welcome_bonus_enabled' => '0',
            'gst_rate_percent' => '3',
        ]);

        $referrer = User::factory()->create([
            'phone' => '9876509003',
            'referral_code' => 'REFCODE2',
            'wallet_balance' => 10,
        ]);

        $referee = $this->userWithTransactionKyc([
            'phone' => '9876509004',
            'mpin' => '1234',
            'wallet_balance' => 50000,
            'referred_by_id' => $referrer->id,
        ]);

        Referral::query()->create([
            'referrer_id' => $referrer->id,
            'referee_id' => $referee->id,
            'referral_code_used' => 'REFCODE2',
            'bonus_amount' => 150,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($referee);

        $this->postJson('/api/v1/buy-metal/purchase', [
            'metal_type' => 'gold',
            'input_mode' => 'currency',
            'amount' => 2500,
            'payment_method' => 'direct',
        ])->assertCreated();

        $referral = Referral::query()->where('referee_id', $referee->id)->first();
        $this->assertSame('credited', $referral->status);
        $this->assertNotNull($referral->credited_at);
        $this->assertEqualsWithDelta(150.0, (float) $referral->bonus_amount, 0.01);

        $referrer->refresh();
        $this->assertEqualsWithDelta(160.0, (float) $referrer->wallet_balance, 0.01);
    }

    public function test_referrer_not_credited_below_threshold(): void
    {
        app(AppSettingService::class)->setMany([
            'referral_bonus_enabled' => '1',
            'referral_bonus_amount' => '100',
            'referral_purchase_threshold' => '2000',
            'welcome_bonus_enabled' => '0',
            'gst_rate_percent' => '3',
        ]);

        $referrer = User::factory()->create([
            'phone' => '9876509005',
            'referral_code' => 'REFCODE3',
            'wallet_balance' => 0,
        ]);

        $referee = $this->userWithTransactionKyc([
            'phone' => '9876509006',
            'mpin' => '1234',
            'wallet_balance' => 50000,
            'referred_by_id' => $referrer->id,
        ]);

        Referral::query()->create([
            'referrer_id' => $referrer->id,
            'referee_id' => $referee->id,
            'referral_code_used' => 'REFCODE3',
            'bonus_amount' => 100,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($referee);

        $this->postJson('/api/v1/buy-metal/purchase', [
            'metal_type' => 'gold',
            'input_mode' => 'currency',
            'amount' => 500,
            'payment_method' => 'direct',
        ])->assertCreated();

        $referral = Referral::query()->where('referee_id', $referee->id)->first();
        $this->assertSame('pending', $referral->status);

        $referrer->refresh();
        $this->assertEqualsWithDelta(0.0, (float) $referrer->wallet_balance, 0.01);

        $spend = app(ReferralService::class)->refereePurchaseTotal($referee->fresh());
        $this->assertLessThan(2000, $spend);
    }
}
