<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountActivityService;
use App\Services\HoldingLotService;
use App\Services\HoldingsPerformanceService;
use App\Support\ApiResponse;
use App\Support\MetalPurchasePayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HoldingsController extends Controller
{
    public function config(HoldingsPerformanceService $service): JsonResponse
    {
        return ApiResponse::success($service->config());
    }

    public function index(Request $request, HoldingLotService $lots): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'metal_type' => ['nullable', Rule::in(['gold', 'silver'])],
        ]);

        return ApiResponse::success(
            $lots->summary($user, $data['metal_type'] ?? null)
        );
    }

    public function performance(Request $request, HoldingsPerformanceService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'metal_type' => ['nullable', Rule::in(['gold', 'silver'])],
            'months' => ['nullable', 'integer', Rule::in([12, 24, 36])],
        ]);

        return ApiResponse::success($service->performance(
            $user,
            $data['metal_type'] ?? (string) config('holdings.default_metal_type', 'gold'),
            (int) ($data['months'] ?? config('holdings.default_months', 12)),
        ));
    }

    public function transactions(Request $request, AccountActivityService $activity): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'metal_type' => ['nullable', Rule::in(['gold', 'silver'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $result = $activity->listHoldingsSellTransactions(
            $user,
            (int) ($data['page'] ?? 1),
            (int) ($data['per_page'] ?? 20),
            $data['metal_type'] ?? null,
        );

        return ApiResponse::successList(
            $result['transactions'],
            '',
            200,
            [
                'filter' => 'sell',
                'metal_type' => $result['metal_type'] ?? null,
                'pagination' => $result['pagination'] ?? [],
            ],
        );
    }

    public function purchase(Request $request, HoldingLotService $lots): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $this->validatedPurchaseRequest($request);

        $result = $lots->purchase($user, $data);

        return ApiResponse::success([
            'purchase' => MetalPurchasePayload::purchase($result),
            'holding' => $result['holding'],
        ], 'Holding purchase successful.', 201);
    }

    public function sell(Request $request, HoldingLotService $lots): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'lot_id' => ['required', 'integer', 'exists:investments,id'],
        ]);

        $result = $lots->sell($user, $data);

        return ApiResponse::success([
            'withdrawal' => app(\App\Services\MetalWithdrawalService::class)->withdrawalPayload($result['withdrawal']),
            'estimate' => $result['estimate'],
            'holding' => $result['holding'],
            'lot' => $result['lot'] ?? null,
            'sellable_grams' => $result['sellable_grams'] ?? null,
            'locked_grams' => $result['locked_grams'] ?? null,
            'sell_after_hours' => $result['sell_after_hours'] ?? 48,
            'auto_approve_hours' => $result['auto_approve_hours'] ?? 2,
            'success' => [
                'title' => 'Sell Requested',
                'message' => 'Your holding sell request is pending admin approval. If not actioned within '
                    .((int) ($result['auto_approve_hours'] ?? 2))
                    .' hours it will auto-approve and payout at the live metal rate to your bank account.',
            ],
        ], 'Holding sell requested successfully.', 201);
    }

    public function claimBonus(Request $request, HoldingLotService $lots): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'metal_type' => ['nullable', Rule::in(['gold', 'silver'])],
            'lot_id' => ['nullable', 'integer', 'exists:investments,id'],
        ]);

        $result = $lots->claimBonus(
            $user,
            $data['metal_type'] ?? null,
            isset($data['lot_id']) ? (int) $data['lot_id'] : null,
        );

        return ApiResponse::success($result, 'Hold anniversary bonus credited.');
    }

    /**
     * Mobile sends already-calculated weight + amount.
     * metal_type defaults to gold; input_mode is not required.
     *
     * @return array{
     *     metal_type: string,
     *     input_mode: string,
     *     amount: float,
     *     weight_grams: float,
     *     payment_method?: string,
     *     transaction_id?: string|null,
     *     client_calculated: bool
     * }
     */
    protected function validatedPurchaseRequest(Request $request): array
    {
        if ($request->filled('Transaction_id') && ! $request->filled('transaction_id')) {
            $request->merge([
                'transaction_id' => $request->input('Transaction_id'),
            ]);
        }

        $data = $request->validate([
            'metal_type' => ['nullable', Rule::in(['gold', 'silver'])],
            'weight_grams' => [
                'required',
                'numeric',
                'min:'.config('buy_metal.min_weight_grams', 0.001),
                'max:'.config('buy_metal.max_weight_grams', 10000),
            ],
            'amount' => ['required', 'numeric', 'min:'.config('buy_metal.min_amount', 1)],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'transaction_id' => ['nullable', 'string', 'max:120'],
        ]);

        return [
            'metal_type' => $data['metal_type'] ?? 'gold',
            'input_mode' => 'weight',
            'weight_grams' => (float) $data['weight_grams'],
            'amount' => (float) $data['amount'],
            'payment_method' => $data['payment_method'] ?? null,
            'transaction_id' => $data['transaction_id'] ?? null,
            'client_calculated' => true,
        ];
    }
}
