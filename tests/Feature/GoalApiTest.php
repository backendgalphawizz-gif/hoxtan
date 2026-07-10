<?php

namespace Tests\Feature;

use App\Models\Investment;
use App\Models\InvestmentGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GoalApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_list_and_view_goals(): void
    {
        $user = User::factory()->create(['phone' => '9876543210', 'mpin' => '1234']);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/goals/config')
            ->assertOk()
            ->assertJsonPath('data.screen.title', 'My Goals')
            ->assertJsonStructure(['data' => ['filters', 'metal_types']]);

        $create = $this->postJson('/api/v1/goals', [
            'title' => 'Dream Home',
            'monthly_contribution' => 5000,
            'target_amount' => 100000,
            'target_date' => now()->addYear()->toDateString(),
            'metal_type' => 'gold',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.goal.title', 'Dream Home')
            ->assertJsonPath('data.goal.target_amount', 100000.0);

        $goalId = $create->json('data.goal.id');

        Investment::query()->create([
            'user_id' => $user->id,
            'metal_type' => 'gold',
            'type' => 'buy',
            'quantity_grams' => 8,
            'rate_per_gram' => 8500,
            'amount' => 68000,
            'gst_amount' => 0,
            'total_amount' => 68000,
            'status' => 'completed',
        ]);

        $this->getJson('/api/v1/goals?status=active')
            ->assertOk()
            ->assertJsonPath('data.summary.active_count', 1)
            ->assertJsonStructure([
                'data' => [
                    'summary' => ['total_goals_value', 'total_goals'],
                    'portfolio' => ['gold_value', 'silver_value', 'total_value'],
                    'goals',
                ],
            ]);

        $this->getJson('/api/v1/goals/'.$goalId)
            ->assertOk()
            ->assertJsonPath('data.goal.id', $goalId)
            ->assertJsonStructure(['data' => ['goal', 'portfolio']]);
    }

    public function test_user_can_update_and_delete_goal(): void
    {
        $user = User::factory()->create(['phone' => '9876543211', 'mpin' => '1234']);
        Sanctum::actingAs($user);

        $goal = InvestmentGoal::query()->create([
            'user_id' => $user->id,
            'title' => 'New Car',
            'metal_type' => 'gold',
            'target_grams' => 20,
            'current_grams' => 0,
            'target_amount' => 200000,
            'monthly_contribution' => 4000,
            'target_date' => now()->addYears(2),
            'status' => 'active',
        ]);

        $this->putJson('/api/v1/goals/'.$goal->id, [
            'title' => 'Dream Car',
            'monthly_contribution' => 6000,
            'target_amount' => 250000,
            'target_date' => now()->addYears(3)->toDateString(),
            'metal_type' => 'gold',
        ])
            ->assertOk()
            ->assertJsonPath('data.goal.title', 'Dream Car');

        $this->deleteJson('/api/v1/goals/'.$goal->id)
            ->assertOk();

        $this->assertDatabaseMissing('investment_goals', ['id' => $goal->id]);
    }

    public function test_user_cannot_access_another_users_goal(): void
    {
        $owner = User::factory()->create(['phone' => '9876543212', 'mpin' => '1234']);
        $other = User::factory()->create(['phone' => '9876543213', 'mpin' => '1234']);

        $goal = InvestmentGoal::query()->create([
            'user_id' => $owner->id,
            'title' => 'Private Goal',
            'metal_type' => 'gold',
            'target_grams' => 10,
            'current_grams' => 0,
            'target_amount' => 100000,
            'monthly_contribution' => 5000,
            'target_date' => now()->addYear(),
            'status' => 'active',
        ]);

        Sanctum::actingAs($other);

        $this->getJson('/api/v1/goals/'.$goal->id)->assertNotFound();
    }
}
