<?php

namespace App\Support;

use App\Models\JewelleryOrder;
use App\Models\JewelleryOrderItem;
use App\Models\Payment;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class OrderPayload
{
    public static function make(JewelleryOrder $order, bool $detailed = false, bool $includeDeliveryOtp = true): array
    {
        $order->loadMissing(['items.product', 'payment', 'emiInstallments', 'invoice']);

        $firstItem = $order->items->first();
        $itemTitle = $firstItem?->product?->name ?? 'Jewellery Order';
        $itemCount = (int) $order->items->sum('quantity');
        $product = $firstItem?->product;
        $productSpecification = $product?->specificationLabel();

        $payload = [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'order_number_display' => '#'.$order->order_number,
            'title' => $itemCount > 1 ? $itemTitle.' +'.($itemCount - 1).' more' : $itemTitle,
            'product_specification' => $productSpecification,
            'item_count' => $itemCount,
            'image_url' => $product?->imageUrl(),
            'image_urls' => $product?->imageUrls() ?? [],
            'status' => $order->status,
            'status_label' => self::statusLabel($order->status),
            'subtotal' => (float) $order->subtotal,
            'metal_value' => (float) $order->metal_value,
            'making_charge_amount' => (float) $order->making_charge_amount,
            'gst_percent' => (float) $order->gst_percent,
            'gst_amount' => (float) $order->gst_amount,
            'discount_amount' => (float) $order->discount_amount,
            'total_amount' => (float) $order->total_amount,
            'total_amount_display' => '₹'.number_format((float) $order->total_amount, 2),
            'payment_mode' => $order->payment_mode,
            'is_emi' => $order->isEmi(),
            'emi' => JewelleryEmiPayload::forOrder($order),
            'shipping_name' => $order->shipping_name,
            'shipping_phone' => $order->shipping_phone,
            'shipping_address' => $order->shipping_address,
            'shipping_address_type' => $order->shipping_address_type,
            'delivery_address' => self::deliveryAddress($order),
            'expected_delivery_date' => $order->expected_delivery_date?->toDateString(),
            'expected_delivery_display' => $order->expected_delivery_date?->format('d F Y'),
            'ordered_at' => $order->created_at?->toIso8601String(),
            'ordered_date' => $order->created_at?->format('d M Y'),
            'ordered_at_display' => $order->created_at?->format('d F Y'),
            'updated_at' => $order->updated_at?->toIso8601String(),
            'tracking' => self::tracking($order),
            'tracking_details' => self::trackingDetails($order),
            'items' => $order->items
                ->map(fn (JewelleryOrderItem $item) => self::item($item))
                ->values()
                ->all(),
            'invoice' => $order->invoice ? [
                'invoice_number' => $order->invoice->invoice_number,
                'total_amount' => (float) $order->invoice->total_amount,
                'issued_at' => $order->invoice->issued_at?->toIso8601String(),
                'download_url' => route('api.invoices.download', $order->invoice),
            ] : null,
        ];

        if ($includeDeliveryOtp) {
            $payload['delivery_otp'] = $order->delivery_otp;
        }

        if ($detailed) {
            $payload['payment'] = self::payment($order->payment);
        }

        return $payload;
    }

    /**
     * @param  Collection<int, JewelleryOrder>  $orders
     */
    public static function collection(Collection $orders, bool $detailed = false): array
    {
        return $orders
            ->map(fn (JewelleryOrder $order) => self::make($order, $detailed))
            ->values()
            ->all();
    }

    public static function item(JewelleryOrderItem $item): array
    {
        $item->loadMissing('product');

        return [
            'id' => $item->id,
            'product_id' => $item->jewellery_product_id,
            'quantity' => (int) $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'line_total' => (float) $item->line_total,
            'line_total_display' => '₹'.number_format((float) $item->line_total, 2),
            'product' => $item->product ? JewelleryProductPayload::make($item->product) : null,
        ];
    }

    public static function payment(?Payment $payment): ?array
    {
        if (! $payment) {
            return null;
        }

        return [
            'id' => $payment->id,
            'reference_id' => $payment->reference_id,
            'amount' => (float) $payment->amount,
            'amount_display' => '₹'.number_format((float) $payment->amount, 2),
            'currency' => $payment->currency,
            'status' => $payment->status,
            'status_label' => Str::headline((string) $payment->status),
            'gateway' => $payment->gateway,
            'gateway_reference' => $payment->gateway_reference,
            'paid_at' => $payment->paid_at?->toIso8601String(),
        ];
    }

    public static function statusLabel(?string $status): string
    {
        return config('account_activity.order_statuses.'.$status, Str::headline((string) $status));
    }

    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     completed: bool,
     *     current: bool,
     *     completed_at: ?string,
     *     completed_at_display: ?string,
     *     expected_at: ?string,
     *     expected_at_display: ?string
     * }>
     */
    public static function tracking(JewelleryOrder $order): array
    {
        if (in_array($order->status, ['cancelled', 'failed'], true)) {
            return [
                [
                    'key' => $order->status,
                    'label' => self::statusLabel($order->status),
                    'completed' => true,
                    'current' => true,
                    'completed_at' => $order->updated_at?->toIso8601String(),
                    'completed_at_display' => $order->updated_at?->format('d F Y, h:i A'),
                    'expected_at' => null,
                    'expected_at_display' => null,
                ],
            ];
        }

        if ($order->isEmi()) {
            return self::emiTracking($order);
        }

        $steps = config('account_activity.order_tracking_steps', []);
        $currentIndex = self::currentTrackingIndex($order);

        return collect($steps)
            ->values()
            ->map(function (array $step, int $index) use ($order, $currentIndex): array {
                $completed = $index <= $currentIndex;
                $current = $index === $currentIndex;
                $at = $completed ? self::trackingTimestamp($order, $step['key']) : null;

                return [
                    'key' => $step['key'],
                    'label' => $step['label'],
                    'completed' => $completed,
                    'current' => $current,
                    'completed_at' => $at,
                    'completed_at_display' => $at
                        ? \Illuminate\Support\Carbon::parse($at)->format('d F Y, h:i A')
                        : null,
                    'expected_at' => (! $completed && $step['key'] === 'delivered')
                        ? $order->expected_delivery_date?->toDateString()
                        : null,
                    'expected_at_display' => (! $completed && $step['key'] === 'delivered' && $order->expected_delivery_date)
                        ? 'Expected: '.$order->expected_delivery_date->format('d M Y')
                        : null,
                ];
            })
            ->all();
    }

    /**
     * EMI track timeline matching the mobile Track Order (EMI) screens.
     *
     * @return list<array<string, mixed>>
     */
    protected static function emiTracking(JewelleryOrder $order): array
    {
        $steps = config('account_activity.emi_order_tracking_steps', []);
        $currentIndex = self::currentEmiTrackingIndex($order);
        $emiFullyPaid = $order->emiInstallmentsFullyPaid();
        $lastPaidAt = null;

        if ($emiFullyPaid) {
            $order->loadMissing('emiInstallments');
            $lastPaidAt = $order->emiInstallments
                ->where('status', 'paid')
                ->sortByDesc(fn ($row) => $row->paid_at?->timestamp ?? 0)
                ->first()
                ?->paid_at;
        }

        return collect($steps)
            ->values()
            ->map(function (array $step, int $index) use ($order, $currentIndex, $emiFullyPaid, $lastPaidAt): array {
                $completed = $index <= $currentIndex;
                $current = $index === $currentIndex && ! (
                    $order->status === 'completed' || filled($order->delivered_at)
                );
                // When fully delivered, keep last step current=false and all completed.
                if (($order->status === 'completed' || filled($order->delivered_at)) && $index === $currentIndex) {
                    $current = false;
                    $completed = true;
                }

                $label = $step['label'];
                if ($step['key'] === 'emi_waiting' && $emiFullyPaid && ! empty($step['completed_label'])) {
                    $label = $step['completed_label'];
                }

                $completedAt = null;
                $expectedAt = null;
                $expectedDisplay = null;

                if ($completed) {
                    $completedAt = match ($step['key']) {
                        'placed', 'reserved' => $order->created_at,
                        'emi_waiting' => $emiFullyPaid ? ($lastPaidAt ?? $order->updated_at) : null,
                        'ready_for_delivery' => $order->dispatched_at
                            ?? ($emiFullyPaid ? ($lastPaidAt ?? $order->updated_at) : null),
                        'delivered' => $order->delivered_at
                            ?? ($order->status === 'completed' ? $order->updated_at : null),
                        default => null,
                    };
                } else {
                    if (in_array($step['key'], ['ready_for_delivery', 'delivered'], true) && $order->expected_delivery_date) {
                        $expectedAt = $order->expected_delivery_date->toDateString();
                        $expectedDisplay = 'Expected: '.$order->expected_delivery_date->format('d M Y');
                    }
                }

                return [
                    'key' => $step['key'],
                    'label' => $label,
                    'completed' => $completed,
                    'current' => $current,
                    'completed_at' => $completedAt?->toIso8601String(),
                    'completed_at_display' => $completedAt?->format('d F Y, h:i A'),
                    'expected_at' => $expectedAt,
                    'expected_at_display' => $expectedDisplay,
                ];
            })
            ->all();
    }

    protected static function currentEmiTrackingIndex(JewelleryOrder $order): int
    {
        if ($order->status === 'completed' || filled($order->delivered_at)) {
            return 4;
        }

        if ($order->dispatched_at || filled($order->tracking_number) || filled($order->driver_id)) {
            return 3;
        }

        if ($order->emiInstallmentsFullyPaid()) {
            return 3;
        }

        // Placed + reserved are done; waiting on EMI installments.
        return 2;
    }

    /**
     * @return array{
     *     name: ?string,
     *     phone: ?string,
     *     address: ?string,
     *     address_type: ?string
     * }
     */
    public static function deliveryAddress(JewelleryOrder $order): array
    {
        return [
            'name' => $order->shipping_name,
            'phone' => $order->shipping_phone,
            'address' => $order->shipping_address,
            'address_type' => $order->shipping_address_type,
        ];
    }

    /**
     * @return array{
     *     tracking_number: ?string,
     *     courier_name: ?string,
     *     dispatched_at: ?string,
     *     delivered_at: ?string,
     *     expected_delivery_date: ?string,
     *     expected_delivery_display: ?string
     * }
     */
    public static function trackingDetails(JewelleryOrder $order): array
    {
        return [
            'tracking_number' => $order->tracking_number,
            'courier_name' => $order->courier_name,
            'dispatched_at' => $order->dispatched_at?->toIso8601String(),
            'delivered_at' => $order->delivered_at?->toIso8601String(),
            'expected_delivery_date' => $order->expected_delivery_date?->toDateString(),
            'expected_delivery_display' => $order->expected_delivery_date?->format('d F Y'),
        ];
    }

    protected static function currentTrackingIndex(JewelleryOrder $order): int
    {
        if ($order->status === 'completed') {
            return 3;
        }

        if ($order->dispatched_at || filled($order->tracking_number)) {
            return 2;
        }

        return (int) (config('account_activity.order_status_tracking_index.'.$order->status) ?? 0);
    }

    protected static function trackingTimestamp(JewelleryOrder $order, string $stepKey): ?string
    {
        $at = match ($stepKey) {
            'placed' => $order->created_at,
            'processing' => $order->status === 'processing' ? ($order->updated_at ?? $order->created_at) : $order->created_at,
            'shipped' => $order->dispatched_at ?? ($order->tracking_number ? $order->updated_at : null),
            'delivered' => $order->delivered_at ?? ($order->status === 'completed' ? $order->updated_at : null),
            default => null,
        };

        return $at?->toIso8601String();
    }
}
