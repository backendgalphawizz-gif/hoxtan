<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\JewelleryOrder;
use App\Models\OldGoldBooking;
use App\Support\DriverTaskPayload;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

class DriverTaskService
{
    /**
     * @return array{
     *     assigned_orders: int,
     *     jewellery_pickups: int,
     *     completed: array{assigned_orders: int, jewellery_pickups: int},
     *     pending: array{assigned_orders: int, jewellery_pickups: int}
     * }
     */
    public function statistics(Driver $driver): array
    {
        $assignedOrders = $this->assignedDeliveriesQuery($driver);
        $assignedPickups = $this->assignedPickupsQuery($driver);

        $completedOrders = (clone $assignedOrders)
            ->where(function (Builder $query): void {
                $query
                    ->where('status', 'completed')
                    ->orWhereNotNull('delivered_at');
            });

        $completedPickups = (clone $assignedPickups)
            ->where('status', 'completed');

        $assignedOrdersCount = (clone $assignedOrders)->count();
        $assignedPickupsCount = (clone $assignedPickups)->count();
        $completedOrdersCount = (clone $completedOrders)->count();
        $completedPickupsCount = (clone $completedPickups)->count();

        return [
            'assigned_orders' => $assignedOrdersCount,
            'jewellery_pickups' => $assignedPickupsCount,
            'completed' => [
                'assigned_orders' => $completedOrdersCount,
                'jewellery_pickups' => $completedPickupsCount,
            ],
            'pending' => [
                'assigned_orders' => max(0, $assignedOrdersCount - $completedOrdersCount),
                'jewellery_pickups' => max(0, $assignedPickupsCount - $completedPickupsCount),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentTasks(Driver $driver, int $limit = 5): array
    {
        $tasks = $this->allTasksCollection($driver, status: 'all');

        return DriverTaskPayload::stripInternal(
            $tasks->take($limit)
        );
    }

    /**
     * @param  array{
     *     type?: string,
     *     status?: string,
     *     page?: int,
     *     per_page?: int
     * }  $filters
     * @return array{
     *     tasks: list<array<string, mixed>>,
     *     pagination: array{
     *         current_page: int,
     *         per_page: int,
     *         total: int,
     *         last_page: int,
     *         has_more: bool,
     *         showing: int
     *     }
     * }
     */
    public function paginatedTasks(Driver $driver, array $filters): array
    {
        $type = $filters['type'] ?? 'all';
        $status = $filters['status'] ?? 'all';
        $perPage = (int) ($filters['per_page'] ?? 10);
        $page = (int) ($filters['page'] ?? 1);

        if ($type === 'delivery') {
            return $this->paginateDeliveries($driver, $status, $perPage, $page);
        }

        if ($type === 'pickup') {
            return $this->paginatePickups($driver, $status, $perPage, $page);
        }

        $tasks = $this->allTasksCollection($driver, $status);
        $total = $tasks->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $lastPage);
        $offset = ($page - 1) * $perPage;
        $slice = $tasks->slice($offset, $perPage)->values();

        return [
            'tasks' => DriverTaskPayload::stripInternal($slice),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'has_more' => $page < $lastPage,
                'showing' => $slice->count(),
            ],
        ];
    }

    /**
     * @return array{
     *     tasks: list<array<string, mixed>>,
     *     pagination: array{
     *         current_page: int,
     *         per_page: int,
     *         total: int,
     *         last_page: int,
     *         has_more: bool,
     *         showing: int
     *     }
     * }
     */
    protected function paginateDeliveries(Driver $driver, string $status, int $perPage, int $page): array
    {
        $query = $this->assignedDeliveriesQuery($driver)
            ->with(['items.product', 'user'])
            ->latest('driver_assigned_at')
            ->latest('id');

        $this->applyDeliveryStatusFilter($query, $status);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'tasks' => collect($paginator->items())
                ->map(fn (JewelleryOrder $order) => DriverTaskPayload::fromDelivery($order))
                ->map(fn (array $task): array => collect($task)->except(['sort_at'])->all())
                ->values()
                ->all(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'has_more' => $paginator->hasMorePages(),
                'showing' => $paginator->count(),
            ],
        ];
    }

    /**
     * @return array{
     *     tasks: list<array<string, mixed>>,
     *     pagination: array{
     *         current_page: int,
     *         per_page: int,
     *         total: int,
     *         last_page: int,
     *         has_more: bool,
     *         showing: int
     *     }
     * }
     */
    protected function paginatePickups(Driver $driver, string $status, int $perPage, int $page): array
    {
        $query = $this->assignedPickupsQuery($driver)
            ->with('user')
            ->latest('driver_assigned_at')
            ->latest('id');

        $this->applyPickupStatusFilter($query, $status);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'tasks' => collect($paginator->items())
                ->map(fn (OldGoldBooking $booking) => DriverTaskPayload::fromPickup($booking))
                ->map(fn (array $task): array => collect($task)->except(['sort_at'])->all())
                ->values()
                ->all(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'has_more' => $paginator->hasMorePages(),
                'showing' => $paginator->count(),
            ],
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function allTasksCollection(Driver $driver, string $status): Collection
    {
        $deliveries = $this->assignedDeliveriesQuery($driver)
            ->with(['items.product', 'user'])
            ->get()
            ->filter(fn (JewelleryOrder $order): bool => $this->matchesDeliveryStatus($order, $status))
            ->map(fn (JewelleryOrder $order) => DriverTaskPayload::fromDelivery($order));

        $pickups = $this->assignedPickupsQuery($driver)
            ->with('user')
            ->get()
            ->filter(fn (OldGoldBooking $booking): bool => $this->matchesPickupStatus($booking, $status))
            ->map(fn (OldGoldBooking $booking) => DriverTaskPayload::fromPickup($booking));

        return $deliveries
            ->concat($pickups)
            ->sortByDesc('sort_at')
            ->values();
    }

    /**
     * @return HasMany<JewelleryOrder, Driver>
     */
    protected function assignedDeliveriesQuery(Driver $driver): HasMany
    {
        return $driver->jewelleryOrders()
            ->where('status', '!=', 'cart');
    }

    /**
     * @return HasMany<OldGoldBooking, Driver>
     */
    protected function assignedPickupsQuery(Driver $driver): HasMany
    {
        return $driver->oldGoldBookings();
    }

    /**
     * @param  Relation<JewelleryOrder, Driver>  $query
     */
    protected function applyDeliveryStatusFilter(Relation $query, string $status): void
    {
        if ($status === 'completed') {
            $query->where(function ($builder): void {
                $builder
                    ->where('status', 'completed')
                    ->orWhereNotNull('delivered_at');
            });

            return;
        }

        if ($status === 'pending') {
            $query
                ->whereNotIn('status', ['completed', 'cancelled', 'failed'])
                ->whereNull('delivered_at');
        }
    }

    /**
     * @param  Relation<OldGoldBooking, Driver>  $query
     */
    protected function applyPickupStatusFilter(Relation $query, string $status): void
    {
        if ($status === 'completed') {
            $query->where('status', 'completed');

            return;
        }

        if ($status === 'pending') {
            $query->whereNotIn('status', ['completed', 'cancelled', 'failed']);
        }
    }

    protected function matchesDeliveryStatus(JewelleryOrder $order, string $status): bool
    {
        return match ($status) {
            'completed' => DriverTaskPayload::isDeliveryCompleted($order),
            'pending' => DriverTaskPayload::isDeliveryPending($order),
            default => true,
        };
    }

    protected function matchesPickupStatus(OldGoldBooking $booking, string $status): bool
    {
        return match ($status) {
            'completed' => DriverTaskPayload::isPickupCompleted($booking),
            'pending' => DriverTaskPayload::isPickupPending($booking),
            default => true,
        };
    }
}
