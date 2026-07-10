<?php

namespace App\Support;

use App\Models\JewelleryOrder;
use App\Models\OldGoldBooking;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DriverTaskPayload
{
    public static function fromDelivery(JewelleryOrder $order): array
    {
        $order->loadMissing(['items.product', 'user']);

        $firstItem = $order->items->first();
        $product = $firstItem?->product;
        $itemTitle = $product?->name ?? 'Jewellery Order';
        $weightGrams = $product?->weight_grams;
        $purity = $product?->purity;
        $scheduledAt = $order->driver_assigned_at ?? $order->expected_delivery_date ?? $order->created_at;
        $driverStatus = DriverDeliveryPayload::resolveStatus($order);

        return array_merge(self::base(
            id: $order->id,
            taskType: 'delivery',
            taskTypeLabel: 'Assigned Order',
            orderId: $order->id,
            pickupId: null,
            referenceId: $order->order_number,
            referenceDisplay: $order->order_number,
            scheduledAt: $scheduledAt,
            title: $itemTitle,
            weightGrams: $weightGrams,
            purity: $purity,
            amount: (float) $order->total_amount,
            customerName: $order->shipping_name ?: $order->user?->name,
            locationLabel: 'Delivery Location',
            locationAddress: $order->shipping_address,
            imageUrl: $product?->imageUrl(),
            status: $order->status,
            statusLabel: OrderPayload::statusLabel($order->status),
            isPending: self::isDeliveryPending($order),
            isCompleted: self::isDeliveryCompleted($order),
        ), [
            'driver_delivery_status' => $driverStatus['key'],
            'driver_delivery_status_label' => $driverStatus['label'],
            'driver_delivery_status_color' => $driverStatus['color'],
        ]);
    }

    public static function fromPickup(OldGoldBooking $booking): array
    {
        $booking->loadMissing('user');

        $scheduledAt = $booking->pickup_scheduled_at
            ?? $booking->driver_assigned_at
            ?? $booking->created_at;

        return self::base(
            id: $booking->id,
            taskType: 'pickup',
            taskTypeLabel: 'Jewellery Pickup',
            orderId: null,
            pickupId: $booking->id,
            referenceId: $booking->booking_number,
            referenceDisplay: str_starts_with((string) $booking->booking_number, '#')
                ? $booking->booking_number
                : '#'.$booking->booking_number,
            scheduledAt: $scheduledAt,
            title: $booking->item_name ?: self::defaultPickupTitle($booking),
            weightGrams: $booking->estimated_weight_grams,
            purity: $booking->purity,
            amount: (float) ($booking->quoted_amount ?? 0),
            customerName: $booking->pickup_name ?: $booking->user?->name,
            locationLabel: 'Pickup Location',
            locationAddress: $booking->pickup_address,
            imageUrl: null,
            status: SellJewelleryPayload::normalizeStatus($booking->status),
            statusLabel: SellJewelleryPayload::statusLabel($booking->status),
            isPending: self::isPickupPending($booking),
            isCompleted: self::isPickupCompleted($booking),
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $tasks
     * @return list<array<string, mixed>>
     */
    public static function stripInternal(Collection $tasks): array
    {
        return $tasks
            ->map(fn (array $task): array => collect($task)->except(['sort_at'])->all())
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $task
     * @return array<string, mixed>
     */
    public static function forDeliveriesSection(array $task): array
    {
        $task = collect($task)->except(['sort_at'])->all();

        $weightGrams = $task['weight_grams'] ?? null;
        $purity = $task['purity'] ?? null;
        $specParts = [];

        if ($weightGrams !== null) {
            $specParts[] = 'Estimated Weight: '.rtrim(rtrim(number_format((float) $weightGrams, 3, '.', ''), '0'), '.').' gm';
        }

        if (filled($purity)) {
            $specParts[] = 'Purity: '.$purity;
        }

        $isDelivery = ($task['task_type'] ?? '') === 'delivery';

        if ($isDelivery) {
            $statusKey = (string) ($task['driver_delivery_status'] ?? 'accepted');
            $statuses = config('driver.delivery.statuses', []);

            $statusTag = [
                'key' => $statusKey,
                'label' => $task['driver_delivery_status_label'] ?? ($statuses[$statusKey]['label'] ?? ''),
                'color' => $task['driver_delivery_status_color'] ?? ($statuses[$statusKey]['color'] ?? 'muted'),
            ];
            $displayIdLabel = 'Order ID';
        } else {
            $statusTag = [
                'key' => 'pickup',
                'label' => (string) ($task['task_type_label'] ?? 'Jewellery Pickup'),
                'color' => 'warning',
            ];
            $displayIdLabel = 'Sell ID';
        }

        return array_merge($task, [
            'display_id' => $task['reference_display'] ?? null,
            'display_id_label' => $displayIdLabel,
            'status_tag' => $statusTag,
            'product_name' => $task['title'] ?? null,
            'product_image_url' => $task['image_url'] ?? null,
            'product_details' => [
                'weight_grams' => $weightGrams !== null ? (float) $weightGrams : null,
                'purity' => $purity,
                'display' => $specParts !== [] ? implode(' | ', $specParts) : ($task['specification_display'] ?? null),
            ],
            'customer' => [
                'name' => $task['customer_name'] ?? null,
            ],
            'location' => [
                'type' => $task['location_label'] ?? null,
                'address' => $task['location_address'] ?? null,
            ],
        ]);
    }

    /**
     * @return list<string>
     */
    public static function deliveriesSectionStatusValues(): array
    {
        return collect(config('driver.deliveries.filters', []))
            ->pluck('value')
            ->all();
    }

    public static function isDeliveryCompleted(JewelleryOrder $order): bool
    {
        return $order->status === 'completed' || filled($order->delivered_at);
    }

    public static function isDeliveryPending(JewelleryOrder $order): bool
    {
        if (self::isDeliveryCompleted($order)) {
            return false;
        }

        return ! in_array($order->status, ['cancelled', 'failed'], true);
    }

    public static function isPickupCompleted(OldGoldBooking $booking): bool
    {
        return SellJewelleryPayload::normalizeStatus($booking->status) === 'completed';
    }

    public static function isPickupPending(OldGoldBooking $booking): bool
    {
        if (self::isPickupCompleted($booking)) {
            return false;
        }

        return ! in_array(SellJewelleryPayload::normalizeStatus($booking->status), ['cancelled', 'failed'], true);
    }

    /**
     * @return array<string, mixed>
     */
    protected static function base(
        int $id,
        string $taskType,
        string $taskTypeLabel,
        ?int $orderId,
        ?int $pickupId,
        string $referenceId,
        string $referenceDisplay,
        mixed $scheduledAt,
        string $title,
        mixed $weightGrams,
        ?string $purity,
        float $amount,
        ?string $customerName,
        string $locationLabel,
        ?string $locationAddress,
        ?string $imageUrl,
        string $status,
        string $statusLabel,
        bool $isPending,
        bool $isCompleted,
    ): array {
        $scheduled = $scheduledAt instanceof Carbon ? $scheduledAt : ($scheduledAt ? Carbon::parse($scheduledAt) : null);
        $detailPath = $taskType === 'delivery'
            ? 'driver/tasks/deliveries/'.$id
            : 'driver/tasks/pickups/'.$id;

        return [
            'id' => $id,
            'task_type' => $taskType,
            'task_type_label' => $taskTypeLabel,
            'resource_key' => $taskType.':'.$id,
            'order_id' => $orderId,
            'pickup_id' => $pickupId,
            'detail_path' => $detailPath,
            'reference_id' => $referenceId,
            'reference_display' => $referenceDisplay,
            'scheduled_at' => $scheduled?->toIso8601String(),
            'scheduled_at_display' => $scheduled?->format('d F Y | h:i A'),
            'title' => $title,
            'weight_grams' => $weightGrams !== null ? (float) $weightGrams : null,
            'purity' => $purity,
            'specification_display' => self::specificationDisplay($weightGrams, $purity),
            'amount' => $amount,
            'amount_display' => '₹ '.number_format($amount, 2),
            'customer_name' => $customerName,
            'location_label' => $locationLabel,
            'location_address' => $locationAddress,
            'image_url' => $imageUrl,
            'status' => $status,
            'status_label' => $statusLabel,
            'is_pending' => $isPending,
            'is_completed' => $isCompleted,
            'sort_at' => $scheduled?->timestamp ?? 0,
        ];
    }

    protected static function specificationDisplay(mixed $weightGrams, ?string $purity): ?string
    {
        $parts = [];

        if ($weightGrams !== null) {
            $parts[] = rtrim(rtrim(number_format((float) $weightGrams, 3, '.', ''), '0'), '.').' gm';
        }

        if (filled($purity)) {
            $parts[] = $purity;
        }

        return $parts !== [] ? implode(' | ', $parts) : null;
    }

    protected static function defaultPickupTitle(OldGoldBooking $booking): string
    {
        $metalLabel = SellJewelleryPayload::metalLabel($booking->metal_type);

        $parts = array_filter([
            $metalLabel !== '' ? $metalLabel.' Jewellery' : null,
            $booking->purity,
        ]);

        return $parts !== [] ? implode(' ', $parts) : 'Gold Jewellery';
    }
}
