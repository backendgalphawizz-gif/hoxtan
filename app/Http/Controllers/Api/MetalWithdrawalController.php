<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MetalWithdrawal;
use App\Models\User;
use App\Services\MetalWithdrawalService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MetalWithdrawalController extends Controller
{
    public function assets(Request $request, MetalWithdrawalService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success($service->assets($user));
    }

    public function screen(Request $request, string $asset, MetalWithdrawalService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success($service->screen($user, $asset));
    }

    public function estimate(Request $request, MetalWithdrawalService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $this->validatedEstimateRequest($request);

        $estimate = $service->estimate(
            $user,
            $data['asset_source'],
            $data['input_mode'],
            isset($data['amount']) ? (float) $data['amount'] : null,
            isset($data['weight_grams']) ? (float) $data['weight_grams'] : null,
        );

        return ApiResponse::success(['estimate' => $estimate]);
    }

    public function store(Request $request, MetalWithdrawalService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $this->validatedCreateRequest($request);

        $result = $service->create($user, $data);

        $isSig = ($data['asset_source'] ?? '') === 'sig';
        $hours = (int) config('withdraw.auto_approve_hours', 2);
        $message = $isSig
            ? 'Withdrawal request sent to Admin successfully. If not actioned within '.$hours.' hours it will auto-approve and credit your registered bank account.'
            : 'Your withdrawal request has been submitted. Funds will be transferred to your registered bank account after approval.';

        return ApiResponse::success([
            'withdrawal' => $service->withdrawalPayload($result['withdrawal']),
            'estimate' => $result['estimate'],
            'success' => [
                'title' => $isSig ? 'SIG Withdrawal Requested' : 'Withdrawal Requested',
                'message' => $message,
            ],
        ], $isSig ? 'Withdrawal request sent to Admin successfully' : 'Withdrawal requested successfully.', 201);
    }

    public function index(Request $request, MetalWithdrawalService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $items = MetalWithdrawal::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (MetalWithdrawal $w) => $service->withdrawalPayload($w))
            ->values()
            ->all();

        return ApiResponse::success(['withdrawals' => $items]);
    }

    public function show(Request $request, MetalWithdrawal $withdrawal, MetalWithdrawalService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($withdrawal->user_id !== $user->id) {
            abort(404);
        }

        return ApiResponse::success([
            'withdrawal' => $service->withdrawalPayload($withdrawal),
        ]);
    }

    /**
     * @return array{asset_source: string, input_mode: string, amount?: float, weight_grams?: float}
     */
    protected function validatedEstimateRequest(Request $request): array
    {
        return $request->validate([
            'asset_source' => ['required', Rule::in(['gold', 'silver', 'sig'])],
            'input_mode' => ['required', Rule::in(['currency', 'weight'])],
            'amount' => ['required_if:input_mode,currency', 'nullable', 'numeric', 'min:'.config('withdraw.min_amount', 1000)],
            'weight_grams' => ['required_if:input_mode,weight', 'nullable', 'numeric', 'min:0.0001'],
        ]);
    }

    /**
     * @return array{asset_source: string, input_mode: string, amount?: float, weight_grams?: float}
     */
    protected function validatedCreateRequest(Request $request): array
    {
        return $this->validatedEstimateRequest($request);
    }
}
