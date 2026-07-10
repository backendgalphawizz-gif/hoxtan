<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InvestmentGoal;
use App\Services\InvestmentGoalService;
use App\Support\ApiResponse;
use App\Support\InvestmentGoalPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GoalController extends Controller
{
    public function config(): JsonResponse
    {
        return ApiResponse::success([
            'screen' => config('goals.screen', []),
            'filters' => config('goals.filters', []),
            'metal_types' => config('goals.metal_types', []),
            'min_monthly_contribution' => (int) config('goals.min_monthly_contribution', 100),
            'min_target_amount' => (int) config('goals.min_target_amount', 1000),
            'portfolio_note' => config('goals.portfolio_note'),
        ]);
    }

    public function index(Request $request, InvestmentGoalService $goals): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', 'string', Rule::in(['active', 'completed'])],
        ]);

        $payload = $goals->listForUser(
            $request->user(),
            $data['status'] ?? 'active',
        );

        return ApiResponse::success($payload);
    }

    public function store(Request $request, InvestmentGoalService $goals): JsonResponse
    {
        $data = $this->validatedGoalData($request);

        $goal = $goals->createForUser($request->user(), $data);
        $portfolio = $goals->portfolioSummary($request->user()->fresh());

        return ApiResponse::success([
            'goal' => InvestmentGoalPayload::goal($goal, $portfolio),
        ], 'Goal created successfully.');
    }

    public function show(Request $request, InvestmentGoal $goal, InvestmentGoalService $goals): JsonResponse
    {
        $this->authorizeGoal($request, $goal);

        $goals->syncUserGoals($request->user());
        $goal->refresh();

        $portfolio = $goals->portfolioSummary($request->user()->fresh());

        return ApiResponse::success([
            'goal' => InvestmentGoalPayload::goal($goal, $portfolio),
            'portfolio' => $portfolio,
        ]);
    }

    public function update(Request $request, InvestmentGoal $goal, InvestmentGoalService $goals): JsonResponse
    {
        $this->authorizeGoal($request, $goal);

        $data = $this->validatedGoalData($request);
        $goal = $goals->updateGoal($goal, $data);
        $portfolio = $goals->portfolioSummary($request->user()->fresh());

        return ApiResponse::success([
            'goal' => InvestmentGoalPayload::goal($goal, $portfolio),
        ], 'Goal updated successfully.');
    }

    public function destroy(Request $request, InvestmentGoal $goal, InvestmentGoalService $goals): JsonResponse
    {
        $this->authorizeGoal($request, $goal);

        $goals->deleteGoal($goal);

        return ApiResponse::success([], 'Goal deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedGoalData(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'monthly_contribution' => [
                'required',
                'numeric',
                'min:'.config('goals.min_monthly_contribution', 100),
            ],
            'target_amount' => [
                'required',
                'numeric',
                'min:'.config('goals.min_target_amount', 1000),
            ],
            'target_date' => ['required', 'date', 'after:today'],
            'metal_type' => ['nullable', 'string', Rule::in(['gold', 'silver'])],
        ], [
            'target_date.after' => 'Target date must be in the future.',
        ]);
    }

    protected function authorizeGoal(Request $request, InvestmentGoal $goal): void
    {
        abort_if((int) $goal->user_id !== (int) $request->user()->id, 404);
    }
}
