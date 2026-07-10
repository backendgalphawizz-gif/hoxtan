<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\JewelleryCheckoutService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JewelleryCheckoutController extends Controller
{
    public function summary(Request $request, JewelleryCheckoutService $checkout): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $this->validatedCheckoutRequest($request);

        $summary = $checkout->summary(
            $user,
            (int) $data['product_id'],
            (int) ($data['quantity'] ?? 1),
            isset($data['address_id']) ? (int) $data['address_id'] : null,
            isset($data['emi_plan_id']) ? (int) $data['emi_plan_id'] : null,
            isset($data['tenure']) ? (int) $data['tenure'] : null,
            isset($data['total_emi_cost']) ? (float) $data['total_emi_cost'] : null,
        );

        return ApiResponse::success($summary);
    }

    public function buyNow(Request $request, JewelleryCheckoutService $checkout): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $this->validatedCheckoutRequest($request);

        $result = $checkout->buyNow(
            $user,
            (int) $data['product_id'],
            (int) ($data['quantity'] ?? 1),
            isset($data['address_id']) ? (int) $data['address_id'] : null,
            $data['payment_type'] ?? 'full',
            isset($data['emi_plan_id']) ? (int) $data['emi_plan_id'] : null,
            isset($data['tenure']) ? (int) $data['tenure'] : null,
            isset($data['total_emi_cost']) ? (float) $data['total_emi_cost'] : null,
        );

        return ApiResponse::success($result, 'Order created successfully. Complete payment to confirm.', 201);
    }

    /**
     * @return array{
     *     product_id: int,
     *     quantity?: int,
     *     address_id?: int,
     *     payment_type?: string,
     *     emi_plan_id?: int,
     *     tenure?: int,
     *     total_emi_cost?: float
     * }
     */
    protected function validatedCheckoutRequest(Request $request): array
    {
        return $request->validate([
            'product_id' => ['required', 'integer', 'exists:jewellery_products,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:10'],
            'address_id' => ['nullable', 'integer', 'exists:user_addresses,id'],
            'payment_type' => ['nullable', 'string', Rule::in(['full', 'emi'])],
            'emi_plan_id' => ['nullable', 'integer', 'exists:jewellery_emi_plans,id'],
            'tenure' => [
                'nullable',
                'integer',
                'min:1',
                Rule::requiredIf(fn (): bool => $request->input('payment_type') === 'emi' && ! $request->filled('emi_plan_id')),
            ],
            'total_emi_cost' => [
                'nullable',
                'numeric',
                'min:0',
                Rule::requiredIf(fn (): bool => $request->input('payment_type') === 'emi' && ! $request->filled('emi_plan_id')),
            ],
        ]);
    }
}
