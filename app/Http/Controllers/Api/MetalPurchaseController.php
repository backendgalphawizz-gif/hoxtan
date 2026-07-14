<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AppSettingService;
use App\Services\MetalPurchaseService;
use App\Services\MetalRateService;
use App\Support\ApiResponse;
use App\Support\MetalPurchasePayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MetalPurchaseController extends Controller
{
    public function config(
        Request $request,
        MetalRateService $rates,
        AppSettingService $settings,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            MetalPurchasePayload::config($user, $rates, $settings),
        );
    }

    public function estimate(Request $request, MetalPurchaseService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $this->validatedEstimateRequest($request);

        $estimate = $service->estimate(
            $user,
            $data['metal_type'],
            $data['input_mode'],
            isset($data['amount']) ? (float) $data['amount'] : null,
            isset($data['weight_grams']) ? (float) $data['weight_grams'] : null,
        );

        return ApiResponse::success(MetalPurchasePayload::estimate($estimate));
    }

    public function purchase(Request $request, MetalPurchaseService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $this->validatedPurchaseRequest($request);

        $result = $service->purchase($user, $data);

        return ApiResponse::success(
            MetalPurchasePayload::purchasePending($result),
            'Razorpay order created. Complete payment to finish purchase.',
            201,
        );
    }

    public function verify(Request $request, MetalPurchaseService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'razorpay_order_id' => ['required', 'string', 'max:100'],
            'razorpay_payment_id' => ['required', 'string', 'max:100'],
            'razorpay_signature' => ['required', 'string', 'max:255'],
        ]);

        $result = $service->verify($user, $data);

        return ApiResponse::success(
            MetalPurchasePayload::purchase($result),
            ($result['already_completed'] ?? false)
                ? 'Purchase already completed.'
                : 'Metal purchased successfully.',
        );
    }

    /**
     * @return array{
     *     metal_type: string,
     *     input_mode: string,
     *     amount?: float,
     *     weight_grams?: float
     * }
     */
    protected function validatedEstimateRequest(Request $request): array
    {
        $data = $request->validate([
            'metal_type' => ['required', Rule::in(['gold', 'silver'])],
            'input_mode' => ['required', Rule::in(['currency', 'weight'])],
            'amount' => ['required_if:input_mode,currency', 'nullable', 'numeric', 'min:'.config('buy_metal.min_amount', 100)],
            'weight_grams' => [
                'required_if:input_mode,weight',
                'nullable',
                'numeric',
                'min:'.config('buy_metal.min_weight_grams', 0.001),
                'max:'.config('buy_metal.max_weight_grams', 10000),
            ],
        ]);

        return $data;
    }

    /**
     * @return array{
     *     metal_type: string,
     *     input_mode: string,
     *     amount?: float,
     *     weight_grams?: float,
     *     payment_method?: string,
     *     transaction_id?: string
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
            'metal_type' => ['required', Rule::in(['gold', 'silver'])],
            'input_mode' => ['required', Rule::in(['currency', 'weight'])],
            'amount' => ['required_if:input_mode,currency', 'nullable', 'numeric', 'min:'.config('buy_metal.min_amount', 100)],
            'weight_grams' => [
                'required_if:input_mode,weight',
                'nullable',
                'numeric',
                'min:'.config('buy_metal.min_weight_grams', 0.001),
                'max:'.config('buy_metal.max_weight_grams', 10000),
            ],
            'payment_method' => ['nullable', Rule::in(['razorpay'])],
            'transaction_id' => ['nullable', 'string', 'max:64', 'unique:investments,reference_id'],
            'Transaction_id' => ['nullable', 'string', 'max:64'],
        ]);

        $data['payment_method'] = $data['payment_method'] ?? 'razorpay';
        $data['transaction_id'] = $data['transaction_id']
            ?? $data['Transaction_id']
            ?? null;
        unset($data['Transaction_id']);

        return $data;
    }
}