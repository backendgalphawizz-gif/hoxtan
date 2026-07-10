<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\JewelleryOrder;
use App\Models\OldGoldBooking;
use App\Services\DriverTaskService;
use App\Support\ApiResponse;
use App\Support\DriverPayload;
use App\Support\DriverTaskPayload;
use App\Support\OrderPayload;
use App\Support\SellJewelleryPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class DriverHomeController extends Controller
{
    public function __construct(
        protected DriverTaskService $driverTaskService,
    ) {}

    public function home(Request $request): JsonResponse
    {
        /** @var Driver $driver */
        $driver = $request->user();

        $data = $request->validate([
            'tasks_limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $tasksLimit = (int) ($data['tasks_limit'] ?? config('driver.home.tasks_preview_limit', 5));

        return ApiResponse::success([
            'driver' => DriverPayload::make($driver),
            'statistics' => $this->driverTaskService->statistics($driver),
            'assigned_tasks' => $this->driverTaskService->recentTasks($driver, $tasksLimit),
            'assigned_tasks_limit' => $tasksLimit,
        ]);
    }

    public function statistics(Request $request): JsonResponse
    {
        /** @var Driver $driver */
        $driver = $request->user();

        return ApiResponse::success([
            'statistics' => $this->driverTaskService->statistics($driver),
        ]);
    }

    public function tasks(Request $request): JsonResponse
    {
        /** @var Driver $driver */
        $driver = $request->user();

        $filters = $request->validate([
            'type' => ['nullable', 'string', Rule::in(['all', 'delivery', 'pickup'])],
            'status' => ['nullable', 'string', Rule::in(['all', 'pending', 'completed'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $result = $this->driverTaskService->paginatedTasks($driver, [
            'type' => $filters['type'] ?? 'all',
            'status' => $filters['status'] ?? 'all',
            'page' => $filters['page'] ?? 1,
            'per_page' => $filters['per_page'] ?? 10,
        ]);

        return ApiResponse::success([
            'tasks' => $result['tasks'],
            'pagination' => $result['pagination'],
            'filters' => config('driver.home.task_filters', []),
        ]);
    }

    public function showDelivery(Request $request, JewelleryOrder $order): JsonResponse
    {
        /** @var Driver $driver */
        $driver = $request->user();

        $this->ensureDeliveryAssignedToDriver($driver, $order);

        $order->load(['items.product', 'payment', 'user']);

        return ApiResponse::success([
            'task' => DriverTaskPayload::fromDelivery($order),
            'order' => OrderPayload::make($order, detailed: true, includeDeliveryOtp: false),
        ]);
    }

    public function showPickup(Request $request, OldGoldBooking $booking): JsonResponse
    {
        /** @var Driver $driver */
        $driver = $request->user();

        $this->ensurePickupAssignedToDriver($driver, $booking);

        $booking->load('user');

        return ApiResponse::success([
            'task' => DriverTaskPayload::fromPickup($booking),
            'pickup' => SellJewelleryPayload::make($booking, detailed: true, includeDeliveryOtp: false),
        ]);
    }

    protected function ensureDeliveryAssignedToDriver(Driver $driver, JewelleryOrder $order): void
    {
        if ($order->driver_id !== $driver->id || $order->status === 'cart') {
            abort(Response::HTTP_NOT_FOUND, 'Resource not found.');
        }
    }

    protected function ensurePickupAssignedToDriver(Driver $driver, OldGoldBooking $booking): void
    {
        if ($booking->driver_id !== $driver->id) {
            abort(Response::HTTP_NOT_FOUND, 'Resource not found.');
        }
    }
}
