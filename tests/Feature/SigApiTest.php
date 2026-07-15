<?php

namespace Tests\Feature;

use App\Models\SigPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SigApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_sig_config_and_estimate(): void
    {
        $user = User::factory()->create(['phone' => '9876543210', 'mpin' => '1234']);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/sig/config')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'frequencies',
                    'metal_types',
                    'preset_amounts',
                    'gst_percent',
                    'rates',
                ],
            ]);

        $this->postJson('/api/v1/sig/estimate', [
            'metal_type' => 'gold',
            'amount' => 500,
            'frequency' => 'weekly',
        ])
            ->assertOk()
            ->assertJsonPath('data.estimate.amount', 500)
            ->assertJsonStructure([
                'data' => [
                    'estimate' => [
                        'rate_per_gram',
                        'gold_grams',
                        'gst_note',
                        'gold_grams_display',
                    ],
                ],
            ]);
    }

    public function test_user_can_activate_pause_resume_and_stop_sig(): void
    {
        $user = User::factory()->create(['phone' => '9876543211', 'mpin' => '1234']);
        Sanctum::actingAs($user);

        $activate = $this->postJson('/api/v1/sig/activate', [
            'metal_type' => 'gold',
            'frequency' => 'weekly',
            'amount' => 500,
        ]);

        $activate->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sig.status', 'active')
            ->assertJsonPath('data.activation.title', 'SIG Activated!')
            ->assertJsonPath('data.sig.amount_display', '₹500/week');

        $this->getJson('/api/v1/sig')
            ->assertOk()
            ->assertJsonPath('data.has_active_plan', true)
            ->assertJsonPath('data.sig.progress_label', '1/52');

        $this->getJson('/api/v1/sig/transactions')
            ->assertOk()
            ->assertJsonPath('data.0.status_label', 'SUCCESS')
            ->assertJsonStructure([
                'data' => [
                    ['title', 'time_display', 'amount_display', 'status_label'],
                ],
            ]);

        $this->postJson('/api/v1/sig/pause')
            ->assertOk()
            ->assertJsonPath('data.sig.status', 'paused');

        $this->postJson('/api/v1/sig/resume')
            ->assertOk()
            ->assertJsonPath('data.sig.status', 'active')
            ->assertJsonStructure(['data' => ['next_auto_debit_display']]);

        $this->postJson('/api/v1/sig/stop')
            ->assertOk()
            ->assertJsonPath('data.sig.status', 'stopped')
            ->assertJsonPath('data.withdrawal.available', true)
            ->assertJsonPath('data.withdrawal.asset_source', 'sig');

        $this->getJson('/api/v1/sig')
            ->assertOk()
            ->assertJsonPath('data.sig.status', 'stopped')
            ->assertJsonPath('data.has_active_plan', false)
            ->assertJsonPath('data.can_activate', true);

        $this->assertSame(0, SigPlan::query()->whereIn('status', ['active', 'paused'])->count());
        $this->assertSame(1, SigPlan::query()->where('status', 'stopped')->count());
    }

    public function test_stopped_sig_can_request_withdrawal_auto_approve_and_show_txn(): void
    {
        \App\Models\MetalRate::query()->create([
            'metal_type' => 'gold',
            'rate_per_gram' => 7000,
            'currency' => 'INR',
        ]);

        $user = User::factory()->create([
            'phone' => '9876543219',
            'mpin' => '1234',
            'name' => 'Sig Withdraw User',
        ]);
        Sanctum::actingAs($user);

        \App\Models\KycDetail::query()->create([
            'user_id' => $user->id,
            'full_name' => 'Sig Withdraw User',
            'bank_name' => 'HDFC Bank',
            'account_holder_name' => 'Sig Withdraw User',
            'account_number' => '12345678904521',
            'ifsc_code' => 'HDFC0001234',
        ]);

        $plan = SigPlan::query()->create([
            'user_id' => $user->id,
            'metal_type' => 'gold',
            'frequency' => 'weekly',
            'amount' => 500,
            'status' => 'stopped',
            'metal_accumulated_grams' => 0.2,
            'total_invested' => 1400,
            'completed_installments' => 1,
            'total_installments' => 52,
            'activated_at' => now()->subDays(10),
            'stopped_at' => now(),
        ]);

        \App\Models\SigInstallment::query()->create([
            'sig_plan_id' => $plan->id,
            'user_id' => $user->id,
            'amount' => 1400,
            'quantity_grams' => 0.2,
            'rate_per_gram' => 7000,
            'status' => 'success',
            'scheduled_at' => now()->subDays(10),
            'processed_at' => now()->subDays(10),
        ]);

        $create = $this->postJson('/api/v1/withdraw', [
            'asset_source' => 'sig',
            'input_mode' => 'weight',
            'weight_grams' => 0.2,
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.withdrawal.status', 'pending')
            ->assertJsonPath('data.withdrawal.asset_source', 'sig');

        $reference = $create->json('data.withdrawal.reference_id');
        $this->assertNotEmpty($reference);

        $this->assertDatabaseHas('metal_withdrawals', [
            'user_id' => $user->id,
            'sig_plan_id' => $plan->id,
            'asset_source' => 'sig',
            'status' => 'pending',
            'reference_id' => $reference,
        ]);

        $this->assertDatabaseHas('sig_installments', [
            'sig_plan_id' => $plan->id,
            'reference_id' => $reference,
            'status' => 'withdrawal_pending',
        ]);

        $txns = $this->getJson('/api/v1/sig/transactions')->assertOk();
        $withdrawalTxn = collect($txns->json('data'))->firstWhere('reference_id', $reference);
        $this->assertNotNull($withdrawalTxn);
        $this->assertSame($reference, $withdrawalTxn['transaction_id']);
        $this->assertSame('withdrawal', $withdrawalTxn['type']);

        // Simulate 2-hour auto-approve window elapsed.
        \App\Models\MetalWithdrawal::query()
            ->where('reference_id', $reference)
            ->update(['auto_approve_at' => now()->subMinute()]);

        $approved = app(\App\Services\MetalWithdrawalService::class)->autoApproveExpired();
        $this->assertSame(1, $approved);

        $this->assertDatabaseHas('metal_withdrawals', [
            'reference_id' => $reference,
            'status' => 'paid',
            'auto_approved' => 1,
        ]);

        $this->assertDatabaseHas('sig_installments', [
            'reference_id' => $reference,
            'status' => 'withdrawal',
        ]);

        $this->assertEquals(0.0, (float) $plan->fresh()->metal_accumulated_grams);

        $txnsAfter = $this->getJson('/api/v1/sig/transactions')->assertOk();
        $done = collect($txnsAfter->json('data'))->firstWhere('reference_id', $reference);
        $this->assertSame('withdrawal', $done['status']);
        $this->assertSame($reference, $done['transaction_id']);
    }
}
