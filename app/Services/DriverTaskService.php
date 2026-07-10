<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\JewelleryOrder;
use App\Models\OldGoldBooking;
use App\Support\DriverTaskPayload;
use Illuminate\Database\Eloquent\Builder;
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
     * @param  array{
     *     type?: string,
     *     status?: string,
     *     search?: string,
     *     page?: int,
     *     per_page?: int
     * }  $filters
     * @return array{
     *     tabs: array{all: int, orders: int, pickups: int},
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
    public function deliveriesSection(Driver $driver, array $filters): array
    {
        $type = $this->normalizeDeliveriesType($filters['type'] ?? 'all');
        $status = $filters['status'] ?? 'all';
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = (int) ($filters['per_page'] ?? config('driver.deliveries.per_page', 10));
        $page = (int) ($filters['page'] ?? 1);

        $tasks = $this->deliveriesSectionCollection($driver, $type, $status, $search);
        $total = $tasks->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $lastPage);
        $offset = ($page - 1) * $perPage;
        $slice = $tasks->slice($offset, $perPage)->values();

        return [
            'tabs' => $this->deliveryTabCounts($driver),
            'tasks' => $slice
                ->map(fn (array $task): array => DriverTaskPayload::forDeliveriesSection($task))
                ->all(),
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
     * @return array{all: int, orders: int, pickups: int}
     */
    public function deliveryTabCounts(Driver $driver): array
    {
        $orders = (clone $this->assignedDeliveriesQuery($driver))->count();
        $pickups = (clone $this->assignedPickupsQuery($driver))->count();

        return [
            'all' => $orders + $pickups,
            'orders' => $orders,
            'pickups' => $pickups,
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

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function deliveriesSectionCollection(
        Driver $driver,
        string $type,
        string $status,
        string $search,
    ): Collection {
        $deliveries = collect();
        $pickups = collect();

        if ($type !== 'pickup') {
            $deliveryQuery = $this->assignedDeliveriesQuery($driver)
                ->with(['items.product', 'user']);

            $this->applyDeliverySearch($deliveryQuery, $search);
            $this->applyDeliveriesSectionStatusFilter($deliveryQuery, $status);

            $deliveries = $deliveryQuery
                ->get()
                ->map(fn (JewelleryOrder $order) => DriverTaskPayload::fromDelivery($order));
        }

        if ($type !== 'delivery' && $type !== 'order' && $type !== 'orders') {
            $pickupQuery = $this->assignedPickupsQuery($driver)
                ->with('user');

            $this->applyPickupSearch($pickupQuery, $search);

            if ($status !== 'all') {
                $this->applyPickupDeliveriesSectionStatusFilter($pickupQuery, $status);
            }

            $pickups = $pickupQuery
                ->get()
                ->map(fn (OldGoldBooking $booking) => DriverTaskPayload::fromPickup($booking));
        }

        return $deliveries
            ->concat($pickups)
            ->sortByDesc('sort_at')
            ->values();
    }

    protected function normalizeDeliveriesType(string $type): string
    {
        return match ($type) {
            'order', 'orders' => 'delivery',
            default => $type,
        };
    }

    /**
     * @param  Relation<JewelleryOrder, Driver>  $query
     */
    protected function applyDeliverySearch(Relation $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';

        $query->where('order_number', 'like', $term);
    }

    /**
     * @param  Relation<OldGoldBooking, Driver>  $query
     */
    protected function applyPickupSearch(Relation $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $normalized = ltrim($search, '#');
        $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $normalized).'%';

        $query->where('booking_number', 'like', $term);
    }

    /**
     * @param  Relation<JewelleryOrder, Driver>  $query
     */
    protected function applyDeliveriesSectionStatusFilter(Relation $query, string $status): void
    {
        match ($status) {
            'new' => $query
                ->where('status', 'processing')
                ->whereNull('picked_up_at')
                ->whereNull('delivered_at')
                ->whereNull('delivery_failure_reason'),
            'accepted' => $query
                ->whereNull('picked_up_at')
                ->whereNull('delivered_at')
                ->whereNull('delivery_failure_reason')
                ->whereNotIn('status', ['cancelled', 'failed', 'completed']),
            'picked_up' => $query
                ->whereNotNull('picked_up_at')
                ->whereNull('delivered_at')
                ->whereNull('delivery_failure_reason')
                ->whereNotIn('status', ['cancelled', 'failed', 'completed']),
            'delivered' => $query->where(function (Builder $builder): void {
                $builder
                    ->where('status', 'completed')
                    ->orWhereNotNull('delivered_at');
            }),
            'cancelled' => $query->where(function (Builder $builder): void {
                $builder
                    ->whereNotNull('delivery_failure_reason')
                    ->orWhereIn('status', ['cancelled', 'failed']);
            }),
            default => null,
        };
    }

    /**
     * @param  Relation<OldGoldBooking, Driver>  $query
     */
    protected function applyPickupDeliveriesSectionStatusFilter(Relation $query, string $status): void
    {
        match ($status) {
            'new' => $query
                ->where('status', 'processing')
                ->whereNull('picked_up_at')
                ->whereNull('pickup_failure_reason'),
            'accepted' => $query
                ->whereNull('picked_up_at')
                ->whereNull('pickup_failure_reason')
                ->whereNotIn('status', ['cancelled', 'failed', 'completed', 'picked_up']),
            'picked_up' => $query
                ->where(function (Builder $builder): void {
                    $builder
                        ->where('status', 'picked_up')
                        ->orWhereNotNull('picked_up_at');
                })
                ->whereNull('pickup_failure_reason')
                ->whereNotIn('status', ['cancelled', 'failed', 'completed']),
            'delivered' => $query->where(function (Builder $builder): void {
                $builder
                    ->where('status', 'completed')
                    ->orWhereNotNull('completed_at');
            }),
            'cancelled' => $query->where(function (Builder $builder): void {
                $builder
                    ->whereNotNull('pickup_failure_reason')
                    ->orWhereIn('status', ['cancelled', 'failed']);
            }),
            default => null,
        };
    }
}
