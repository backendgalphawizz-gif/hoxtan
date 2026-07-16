<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JewelleryOrder;
use App\Services\InvoiceService;
use App\Services\JewelleryEmiCancellationService;
use App\Services\JewelleryEmiService;
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
            ->with(['items.product', 'items.variant', 'payment', 'emiInstallments', 'invoice', 'driver'])
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

    public function show(Request $request, JewelleryOrder $order, InvoiceService $invoices): JsonResponse
    {
        $this->ensureOwnedByUser($request, $order);

        $order->load(['items.product', 'items.variant', 'payment', 'emiInstallments', 'invoice', 'driver']);

        if (! $order->invoice) {
            $invoices->generateForJewelleryOrder($order);
            $order->load('invoice');
        }

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

    public function payAllEmiPreview(Request $request, JewelleryOrder $order, JewelleryEmiService $emi): JsonResponse
    {
        $this->ensureOwnedByUser($request, $order);

        return ApiResponse::success([
            'pay_all' => $emi->payAllPreview($order, $request->user()),
        ]);
    }

    public function payAllEmi(Request $request, JewelleryOrder $order, JewelleryEmiService $emi): JsonResponse
    {
        $this->ensureOwnedByUser($request, $order);

        $data = $request->validate([
            'payment_method' => ['nullable', 'string', Rule::in(['razorpay', 'wallet'])],
        ]);

        $result = $emi->payAllRemaining(
            $order,
            $request->user(),
            $data['payment_method'] ?? 'razorpay',
        );

        $message = ($result['requires_verification'] ?? false)
            ? 'Razorpay order created. Complete payment and call verify.'
            : 'All remaining EMIs paid successfully.';

        return ApiResponse::success([
            'amount_paid' => $result['amount_paid'],
            'amount_paid_display' => $result['amount_paid_display'],
            'installments_paid' => $result['installments_paid'],
            'payment_method' => $result['payment_method'],
            'requires_verification' => (bool) ($result['requires_verification'] ?? false),
            'wallet_balance' => $result['wallet_balance'],
            'fully_paid' => $result['fully_paid'],
            'delivery_unlocked' => $result['delivery_unlocked'],
            'razorpay' => $result['razorpay'] ?? null,
            'order' => OrderPayload::make($result['order'], detailed: true),
        ], $message);
    }

    public function verifyPayAllEmi(Request $request, JewelleryOrder $order, JewelleryEmiService $emi): JsonResponse
    {
        $this->ensureOwnedByUser($request, $order);

        $data = $request->validate([
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
        ]);

        $result = $emi->verifyRazorpayPayAll(
            $order,
            $request->user(),
            $data['razorpay_order_id'],
            $data['razorpay_payment_id'],
            $data['razorpay_signature'],
        );

        return ApiResponse::success([
            'amount_paid' => $result['amount_paid'],
            'amount_paid_display' => $result['amount_paid_display'],
            'installments_paid' => $result['installments_paid'],
            'payment_method' => $result['payment_method'],
            'already_completed' => (bool) ($result['already_completed'] ?? false),
            'wallet_balance' => $result['wallet_balance'],
            'fully_paid' => $result['fully_paid'],
            'delivery_unlocked' => $result['delivery_unlocked'],
            'order' => OrderPayload::make($result['order'], detailed: true),
        ], ($result['already_completed'] ?? false)
            ? 'EMI pay-all already completed.'
            : 'All remaining EMIs paid successfully.');
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
