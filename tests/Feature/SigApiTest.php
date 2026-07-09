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
            ->assertJsonPath('data.transactions.0.status_label', 'SUCCESS')
            ->assertJsonStructure([
                'data' => [
                    'transactions' => [
                        ['title', 'time_display', 'amount_display', 'status_label'],
                    ],
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
            ->assertJsonPath('data.sig.status', 'stopped');

        $this->assertSame(0, SigPlan::query()->whereIn('status', ['active', 'paused'])->count());
    }
}
