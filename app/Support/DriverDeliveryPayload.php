<?php

namespace App\Support;

use App\Models\JewelleryOrder;
use App\Models\JewelleryOrderItem;
use App\Models\Payment;
use Illuminate\Support\Carbon;

class DriverDeliveryPayload
{
    public static function config(): array
    {
        return [
            'otp_length' => (int) config('driver.delivery.otp_length', 4),
            'failure_reasons' => config('driver.delivery.failure_reasons', []),
            'statuses' => config('driver.delivery.statuses', []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function make(JewelleryOrder $order): array
    {
        $order->loadMissing(['items.product', 'payment', 'user']);

        $status = self::resolveStatus($order);
        $scheduledAt = $order->driver_assigned_at ?? $order->expected_delivery_date ?? $order->created_at;
        $scheduled = $scheduledAt instanceof Carbon ? $scheduledAt : Carbon::parse((string) $scheduledAt);
        $firstItem = $order->items->first();
        $product = $firstItem?->product;
        $originalAmount = round((float) $order->subtotal + (float) $order->gst_amount, 2);

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'order_number_display' => '#'.$order->order_number,
            'scheduled_at' => $scheduled->toIso8601String(),
            'scheduled_at_display' => $scheduled->format('d F Y | h:i A'),
            'driver_delivery_status' => $status['key'],
            'driver_delivery_status_label' => $status['label'],
            'driver_delivery_status_color' => $status['color'],
            'available_actions' => self::availableActions($order, $status['key']),
            'product' => [
                'title' => $product?->name ?? 'Jewellery Order',
                'image_url' => $product?->imageUrl(),
                'image_urls' => $product?->imageUrls() ?? [],
                'weight_grams' => $product?->weight_grams !== null ? (float) $product->weight_grams : null,
                'purity' => $product?->purity,
                'specification_display' => self::specificationDisplay($product?->weight_grams, $product?->purity),
                'amount' => (float) $order->total_amount,
                'amount_display' => self::inr((float) $order->total_amount),
            ],
            'customer' => [
                'name' => $order->shipping_name ?: $order->user?->name,
                'phone' => $order->shipping_phone ?: $order->user?->phone,
                'phone_display' => self::phoneDisplay($order->shipping_phone ?: $order->user?->phone),
            ],
            'delivery_location' => [
                'label' => 'Delivery Location',
                'address' => $order->shipping_address,
            ],
            'price_breakup' => [
                'metal_value' => (float) $order->metal_value,
                'metal_value_display' => self::inr((float) $order->metal_value),
                'making_charges' => (float) $order->making_charge_amount,
                'making_charges_display' => self::inr((float) $order->making_charge_amount),
                'gst_percent' => (float) $order->gst_percent,
                'gst_amount' => (float) $order->gst_amount,
                'gst_amount_display' => self::inr((float) $order->gst_amount),
                'original_amount' => $originalAmount,
                'original_amount_display' => self::inr($originalAmount),
                'discount_amount' => (float) $order->discount_amount,
                'discount_amount_display' => self::inr((float) $order->discount_amount),
                'total_amount' => (float) $order->total_amount,
                'total_amount_display' => self::inr((float) $order->total_amount),
            ],
            'payment' => self::payment($order->payment),
            'picked_up_at' => $order->picked_up_at?->toIso8601String(),
            'delivered_at' => $order->delivered_at?->toIso8601String(),
            'delivery_failure_reason' => $order->delivery_failure_reason,
            'delivery_failure_reason_label' => self::failureReasonLabel($order->delivery_failure_reason),
            'delivery_proof_image_url' => self::proofImageUrl($order->delivery_proof_image),
            'items' => $order->items
                ->map(fn (JewelleryOrderItem $item) => OrderPayload::item($item))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{key: string, label: string, color: string}
     */
    public static function resolveStatus(JewelleryOrder $order): array
    {
        $statuses = config('driver.delivery.statuses', []);

        if (filled($order->delivery_failure_reason) || in_array($order->status, ['cancelled', 'failed'], true)) {
            return [
                'key' => 'cancelled',
                'label' => $statuses['cancelled']['label'] ?? 'Cancelled',
                'color' => $statuses['cancelled']['color'] ?? 'danger',
            ];
        }

        if (self::isDelivered($order)) {
            return [
                'key' => 'delivered',
                'label' => $statuses['delivered']['label'] ?? 'Delivered',
                'color' => $statuses['delivered']['color'] ?? 'success',
            ];
        }

        if (filled($order->picked_up_at)) {
            return [
                'key' => 'picked_up',
                'label' => $statuses['picked_up']['label'] ?? 'Picked Up',
                'color' => $statuses['picked_up']['color'] ?? 'warning',
            ];
        }

        return [
            'key' => 'accepted',
            'label' => $statuses['accepted']['label'] ?? 'Accepted',
            'color' => $statuses['accepted']['color'] ?? 'muted',
        ];
    }

    public static function isDelivered(JewelleryOrder $order): bool
    {
        return $order->status === 'completed' || filled($order->delivered_at);
    }

    /**
     * @return list<array{key: string, label: string, method: string, path: string}>
     */
    public static function availableActions(JewelleryOrder $order, ?string $statusKey = null): array
    {
        $statusKey ??= self::resolveStatus($order)['key'];

        return match ($statusKey) {
            'accepted' => [
                self::action('picked_up', 'Picked Up', $order, 'picked-up'),
                self::action('unable_to_deliver', 'Unable to deliver', $order, 'unable-to-deliver'),
            ],
            'picked_up' => [
                self::action('verify_delivery', 'Delivered', $order, 'verify-delivery'),
                self::action('unable_to_deliver', 'Unable to deliver', $order, 'unable-to-deliver'),
            ],
            default => [],
        };
    }

    public static function failureReasonLabel(?string $reason): ?string
    {
        if (! filled($reason)) {
            return null;
        }

        $options = collect(config('driver.delivery.failure_reasons', []))
            ->firstWhere('value', $reason);

        return $options['label'] ?? str($reason)->headline()->toString();
    }

    public static function failureReasonValues(): array
    {
        return collect(config('driver.delivery.failure_reasons', []))
            ->pluck('value')
            ->all();
    }

    /**
     * @return array{key: string, label: string, method: string, path: string}
     */
    protected static function action(string $key, string $label, JewelleryOrder $order, string $endpoint): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'method' => 'POST',
            'path' => 'driver/tasks/deliveries/'.$order->id.'/'.$endpoint,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected static function payment(?Payment $payment): ?array
    {
        if (! $payment) {
            return null;
        }

        $method = match (true) {
            $payment->status === 'completed' && filled($payment->gateway) => 'Paid by '.str($payment->gateway)->headline(),
            $payment->status === 'completed' => 'Paid by Bank Transfer',
            default => 'Payment Pending',
        };

        return [
            'status' => $payment->status,
            'status_label' => str($payment->status)->headline()->toString(),
            'method_label' => $method,
            'amount' => (float) $payment->amount,
            'amount_display' => self::inr((float) $payment->amount),
            'reference_id' => $payment->reference_id,
        ];
    }

    protected static function proofImageUrl(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        return asset('storage/'.$path);
    }

    protected static function specificationDisplay(mixed $weightGrams, ?string $purity): ?string
    {
        $parts = [];

        if ($weightGrams !== null) {
            $parts[] = 'Estimated Weight: '.rtrim(rtrim(number_format((float) $weightGrams, 3, '.', ''), '0'), '.').' gm';
        }

        if (filled($purity)) {
            $parts[] = 'Purity: '.$purity;
        }

        return $parts !== [] ? implode(' | ', $parts) : null;
    }

    protected static function phoneDisplay(?string $phone): ?string
    {
        if (! filled($phone)) {
            return null;
        }

        $normalized = preg_replace('/\D+/', '', $phone) ?? $phone;

        if (strlen($normalized) === 10) {
            return '+91 '.$normalized;
        }

        return $phone;
    }

    protected static function inr(float $amount): string
    {
        return '₹ '.number_format($amount, 2);
    }
}
