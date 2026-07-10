<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\JewelleryOrder;
use App\Services\DriverDeliveryService;
use App\Support\ApiResponse;
use App\Support\DriverDeliveryPayload;
use App\Support\DriverTaskPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class DriverDeliveryController extends Controller
{
    public function __construct(
        protected DriverDeliveryService $deliveryService,
    ) {}

    public function config(): JsonResponse
    {
        return ApiResponse::success(DriverDeliveryPayload::config());
    }

    public function show(Request $request, JewelleryOrder $order): JsonResponse
    {
        /** @var Driver $driver */
        $driver = $request->user();

        $this->ensureAssigned($driver, $order);

        $order->load(['items.product', 'payment', 'user']);

        return ApiResponse::success([
            'task' => DriverTaskPayload::fromDelivery($order),
            'delivery' => DriverDeliveryPayload::make($order),
        ]);
    }

    public function markPickedUp(Request $request, JewelleryOrder $order): JsonResponse
    {
        /** @var Driver $driver */
        $driver = $request->user();

        $order = $this->deliveryService->markPickedUp($driver, $order);

        return ApiResponse::success([
            'task' => DriverTaskPayload::fromDelivery($order),
            'delivery' => DriverDeliveryPayload::make($order),
        ], 'Order marked as picked up.');
    }

    public function verifyDelivery(Request $request, JewelleryOrder $order): JsonResponse
    {
        /** @var Driver $driver */
        $driver = $request->user();

        $data = $request->validate([
            'otp' => ['required', 'digits:'.config('driver.delivery.otp_length', 4)],
            'proof_image' => ['required', 'image', 'max:4096'],
        ]);

        $order = $this->deliveryService->verifyDelivery(
            $driver,
            $order,
            $data['otp'],
            $request->file('proof_image'),
        );

        return ApiResponse::success([
            'task' => DriverTaskPayload::fromDelivery($order),
            'delivery' => DriverDeliveryPayload::make($order),
        ], 'Order delivered successfully.');
    }

    public function markUnableToDeliver(Request $request, JewelleryOrder $order): JsonResponse
    {
        /** @var Driver $driver */
        $driver = $request->user();

        $data = $request->validate([
            'reason' => ['required', 'string', Rule::in(DriverDeliveryPayload::failureReasonValues())],
        ]);

        $order = $this->deliveryService->markUnableToDeliver($driver, $order, $data['reason']);

        return ApiResponse::success([
            'task' => DriverTaskPayload::fromDelivery($order),
            'delivery' => DriverDeliveryPayload::make($order),
        ], 'Delivery marked as undeliverable.');
    }

    protected function ensureAssigned(Driver $driver, JewelleryOrder $order): void
    {
        if ($order->driver_id !== $driver->id || $order->status === 'cart') {
            abort(Response::HTTP_NOT_FOUND, 'Resource not found.');
        }
    }
}
