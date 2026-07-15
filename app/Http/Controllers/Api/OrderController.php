<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JewelleryOrder;
use App\Services\JewelleryEmiCancellationService;
use App\Support\ApiResponse;
use App\Support\OrderPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function config(): JsonResponse
    {
        return ApiResponse::success([
            'status_filters' => config('account_activity.order_filters', []),
            'statuses' => config('account_activity.order_statuses', []),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', 'string', Rule::in(['all', 'pending', 'processing', 'completed', 'cancelled', 'failed'])],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $perPage = (int) ($data['per_page'] ?? 10);
        $status = $data['status'] ?? 'all';

        $query = $request->user()
            ->jewelleryOrders()
            ->where('status', '!=', 'cart')
            ->with(['items.product', 'payment', 'emiInstallments', 'invoice'])
            ->latest('id');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if (filled($data['search'] ?? null)) {
            $search = $data['search'];
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('order_number', 'like', '%'.$search.'%')
                    ->orWhere('shipping_name', 'like', '%'.$search.'%');
            });
        }

        $orders = $query->paginate($perPage);

        return ApiResponse::success([
            'orders' => OrderPayload::collection($orders->getCollection()),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'last_page' => $orders->lastPage(),
                'has_more' => $orders->hasMorePages(),
                'showing' => $orders->count(),
            ],
        ]);
    }

    public function show(Request $request, JewelleryOrder $order): JsonResponse
    {
        $this->ensureOwnedByUser($request, $order);

        $order->load(['items.product', 'payment', 'emiInstallments', 'invoice']);

        return ApiResponse::success([
            'order' => OrderPayload::make($order, detailed: true),
        ]);
    }

    public function cancelEmiPreview(Request $request, JewelleryOrder $order, JewelleryEmiCancellationService $cancellation): JsonResponse
    {
        $this->ensureOwnedByUser($request, $order);

        return ApiResponse::success([
            'cancellation' => $cancellation->preview($order),
        ]);
    }

    public function cancelEmi(Request $request, JewelleryOrder $order, JewelleryEmiCancellationService $cancellation): JsonResponse
    {
        $this->ensureOwnedByUser($request, $order);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $cancellation->cancel($order, $request->user(), $data['reason'] ?? null);

        return ApiResponse::success([
            'refund_request' => $result['refund_request'],
            'order' => OrderPayload::make($result['order']->loadMissing(['items.product', 'payment', 'emiInstallments', 'emiRefundRequests']), detailed: true),
        ], 'EMI order cancelled. Refund request sent for admin approval.');
    }

    protected function ensureOwnedByUser(Request $request, JewelleryOrder $order): void
    {
        if ($order->user_id !== $request->user()->id || $order->status === 'cart') {
            throw ValidationException::withMessages([
                'order' => ['Order not found.'],
            ])->status(404);
        }
    }
}
