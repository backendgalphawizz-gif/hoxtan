<?php

namespace App\Support;

use App\Models\OldGoldBooking;
use Illuminate\Support\Carbon;

class DriverPickupPayload
{
    public static function config(): array
    {
        return [
            'otp_length' => (int) config('driver.pickup.otp_length', 4),
            'failure_reasons' => config('driver.pickup.failure_reasons', []),
            'statuses' => config('driver.pickup.statuses', []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function make(OldGoldBooking $booking): array
    {
        $booking->loadMissing('user');

        $status = self::resolveStatus($booking);
        $scheduledAt = $booking->pickup_scheduled_at
            ?? $booking->driver_assigned_at
            ?? $booking->created_at;
        $scheduled = $scheduledAt instanceof Carbon ? $scheduledAt : Carbon::parse((string) $scheduledAt);
        $weightGrams = $booking->estimated_weight_grams !== null
            ? (float) $booking->estimated_weight_grams
            : null;

        return [
            'id' => $booking->id,
            'booking_number' => $booking->booking_number,
            'booking_number_display' => '#'.$booking->booking_number,
            'scheduled_at' => $scheduled->toIso8601String(),
            'scheduled_at_display' => $scheduled->format('d F Y | h:i A'),
            'driver_pickup_status' => $status['key'],
            'driver_pickup_status_label' => $status['label'],
            'driver_pickup_status_color' => $status['color'],
            'available_actions' => self::availableActions($booking, $status['key']),
            'item' => [
                'title' => $booking->item_name ?: self::defaultItemTitle($booking),
                'metal_type' => $booking->metal_type,
                'metal_type_label' => SellJewelleryPayload::metalLabel($booking->metal_type),
                'purity' => $booking->purity,
                'weight_grams' => $weightGrams,
                'weight_display' => $weightGrams !== null
                    ? rtrim(rtrim(number_format($weightGrams, 2, '.', ''), '0'), '.').'g (Approx)'
                    : null,
                'specification_display' => self::specificationDisplay($weightGrams, $booking->purity),
                'estimated_value' => $booking->quoted_amount !== null ? (float) $booking->quoted_amount : null,
                'estimated_value_display' => $booking->quoted_amount !== null
                    ? self::inr((float) $booking->quoted_amount).' (Approx)'
                    : null,
            ],
            'customer' => [
                'name' => $booking->pickup_name ?: $booking->user?->name,
                'phone' => $booking->pickup_phone ?: $booking->user?->phone,
                'phone_display' => self::phoneDisplay($booking->pickup_phone ?: $booking->user?->phone),
            ],
            'pickup_location' => [
                'label' => 'Pickup Location',
                'address' => $booking->pickup_address,
            ],
            'sell_from' => [
                'value' => $booking->sell_location,
                'label' => SellJewelleryPayload::sellLocationLabel($booking->sell_location),
            ],
            'verification' => [
                'customer_verified' => filled($booking->customer_verified_at),
                'customer_verified_at' => $booking->customer_verified_at?->toIso8601String(),
                'proof_uploaded' => self::hasProofImages($booking),
                'proof_image_urls' => self::proofImageUrls($booking),
            ],
            'driver_accepted_at' => $booking->driver_accepted_at?->toIso8601String(),
            'picked_up_at' => $booking->picked_up_at?->toIso8601String(),
            'completed_at' => $booking->completed_at?->toIso8601String(),
            'pickup_failure_reason' => $booking->pickup_failure_reason,
            'pickup_failure_reason_label' => self::failureReasonLabel($booking->pickup_failure_reason),
            'booking_status' => SellJewelleryPayload::normalizeStatus($booking->status),
            'booking_status_label' => SellJewelleryPayload::statusLabel($booking->status),
        ];
    }

    /**
     * @return array{key: string, label: string, color: string}
     */
    public static function resolveStatus(OldGoldBooking $booking): array
    {
        $statuses = config('driver.pickup.statuses', []);

        if (filled($booking->pickup_failure_reason) || in_array($booking->status, ['cancelled', 'failed'], true)) {
            return [
                'key' => 'cancelled',
                'label' => $statuses['cancelled']['label'] ?? 'Cancelled',
                'color' => $statuses['cancelled']['color'] ?? 'danger',
            ];
        }

        if (self::isCollected($booking)) {
            return [
                'key' => 'collected',
                'label' => $statuses['collected']['label'] ?? 'Collected',
                'color' => $statuses['collected']['color'] ?? 'success',
            ];
        }

        if (self::hasProofImages($booking)) {
            return [
                'key' => 'proof_uploaded',
                'label' => $statuses['proof_uploaded']['label'] ?? 'Proof Uploaded',
                'color' => $statuses['proof_uploaded']['color'] ?? 'info',
            ];
        }

        if (filled($booking->customer_verified_at)) {
            return [
                'key' => 'verified',
                'label' => $statuses['verified']['label'] ?? 'Verified',
                'color' => $statuses['verified']['color'] ?? 'primary',
            ];
        }

        return [
            'key' => 'processing',
            'label' => $statuses['processing']['label'] ?? 'Processing',
            'color' => $statuses['processing']['color'] ?? 'muted',
        ];
    }

    public static function isCollected(OldGoldBooking $booking): bool
    {
        return in_array(SellJewelleryPayload::normalizeStatus($booking->status), ['picked_up', 'completed'], true)
            || filled($booking->picked_up_at);
    }

    public static function hasProofImages(OldGoldBooking $booking): bool
    {
        return is_array($booking->pickup_proof_images) && $booking->pickup_proof_images !== [];
    }

    /**
     * @return list<array{key: string, label: string, method: string, path: string}>
     */
    public static function availableActions(OldGoldBooking $booking, ?string $statusKey = null): array
    {
        $statusKey ??= self::resolveStatus($booking)['key'];

        return match ($statusKey) {
            'processing' => [
                self::action('verify_customer', 'Verify Customer & Jewellery', $booking, 'verify-customer'),
                self::action('unable_to_pickup', 'Unable to pickup', $booking, 'unable-to-pickup'),
            ],
            'verified' => [
                self::action('upload_proof', 'Upload & Continue', $booking, 'upload-proof'),
                self::action('unable_to_pickup', 'Unable to pickup', $booking, 'unable-to-pickup'),
            ],
            'proof_uploaded' => [
                self::action('verify_otp', 'Verify OTP', $booking, 'verify-otp'),
            ],
            default => [],
        };
    }

    public static function failureReasonLabel(?string $reason): ?string
    {
        if (! filled($reason)) {
            return null;
        }

        $options = collect(config('driver.pickup.failure_reasons', []))
            ->firstWhere('value', $reason);

        return $options['label'] ?? str($reason)->headline()->toString();
    }

    /**
     * @return list<string>
     */
    public static function failureReasonValues(): array
    {
        return collect(config('driver.pickup.failure_reasons', []))
            ->pluck('value')
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function proofImageUrls(OldGoldBooking $booking): array
    {
        if (! is_array($booking->pickup_proof_images)) {
            return [];
        }

        return collect($booking->pickup_proof_images)
            ->filter(fn ($path) => filled($path))
            ->map(fn (string $path): string => asset('storage/'.$path))
            ->values()
            ->all();
    }

    /**
     * @return array{key: string, label: string, method: string, path: string}
     */
    protected static function action(string $key, string $label, OldGoldBooking $booking, string $endpoint): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'method' => 'POST',
            'path' => 'driver/tasks/pickups/'.$booking->id.'/'.$endpoint,
        ];
    }

    protected static function defaultItemTitle(OldGoldBooking $booking): string
    {
        $metalLabel = SellJewelleryPayload::metalLabel($booking->metal_type);

        return filled($metalLabel) ? $metalLabel.' Jewellery' : 'Gold Jewellery';
    }

    protected static function specificationDisplay(?float $weightGrams, ?string $purity): ?string
    {
        $parts = [];

        if ($weightGrams !== null) {
            $parts[] = 'Estimated Weight: '.rtrim(rtrim(number_format($weightGrams, 2, '.', ''), '0'), '.').' gm';
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
        return '₹'.number_format($amount, 2);
    }
}
