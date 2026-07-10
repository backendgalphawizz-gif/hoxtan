<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Services\DriverTaskService;
use App\Support\ApiResponse;
use App\Support\DriverTaskPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DriverDeliveriesController extends Controller
{
    public function __construct(
        protected DriverTaskService $driverTaskService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Driver $driver */
        $driver = $request->user();

        $filters = $request->validate([
            'type' => ['nullable', 'string', Rule::in(['all', 'order', 'orders', 'delivery', 'pickup'])],
            'status' => ['nullable', 'string', Rule::in(DriverTaskPayload::deliveriesSectionStatusValues())],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $result = $this->driverTaskService->deliveriesSection($driver, [
            'type' => $filters['type'] ?? 'all',
            'status' => $filters['status'] ?? 'all',
            'search' => $filters['search'] ?? '',
            'page' => $filters['page'] ?? 1,
            'per_page' => $filters['per_page'] ?? config('driver.deliveries.per_page', 10),
        ]);

        $tabs = collect(config('driver.deliveries.tabs', []))
            ->map(fn (array $tab): array => [
                'value' => $tab['value'],
                'label' => $tab['label'],
                'count' => $result['tabs'][$tab['value'] === 'order' ? 'orders' : $tab['value']] ?? 0,
            ])
            ->values()
            ->all();

        return ApiResponse::success([
            'tabs' => $tabs,
            'search_placeholder' => config('driver.deliveries.search_placeholder'),
            'filters' => config('driver.deliveries.filters', []),
            'tasks' => $result['tasks'],
            'pagination' => $result['pagination'],
        ]);
    }
}
