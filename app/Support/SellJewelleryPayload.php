<?php

namespace App\Support;

use App\Models\OldGoldBooking;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SellJewelleryPayload
{
    public static function make(OldGoldBooking $booking, bool $detailed = false, bool $includeDeliveryOtp = true): array
    {
        $payload = [
            'id' => $booking->id,
            'booking_number' => $booking->booking_number,
            'booking_number_display' => '#'.$booking->booking_number,
            'item_name' => $booking->item_name ?? self::defaultItemName($booking),
            'metal_type' => $booking->metal_type,
            'metal_type_label' => self::metalLabel($booking->metal_type),
            'purity' => $booking->purity,
            'weight_grams' => $booking->estimated_weight_grams !== null
                ? (float) $booking->estimated_weight_grams
                : null,
            'weight_display' => $booking->estimated_weight_grams !== null
                ? rtrim(rtrim(number_format((float) $booking->estimated_weight_grams, 3, '.', ''), '0'), '.').' gm'
                : null,
            'rate_per_gram' => $booking->rate_per_gram !== null ? (float) $booking->rate_per_gram : null,
            'estimated_value' => $booking->quoted_amount !== null ? (float) $booking->quoted_amount : null,
            'estimated_value_display' => $booking->quoted_amount !== null
                ? '₹'.number_format((float) $booking->quoted_amount, 2)
                : null,
            'final_amount' => $booking->final_amount !== null ? (float) $booking->final_amount : null,
            'identity_owner' => $booking->identity_owner,
            'identity_owner_label' => self::identityOwnerLabel($booking->identity_owner),
            'sell_location' => $booking->sell_location,
            'sell_location_label' => self::sellLocationLabel($booking->sell_location),
            'status' => self::normalizeStatus($booking->status),
            'status_label' => self::statusLabel($booking->status),
            'pickup_address' => $booking->pickup_address,
            'pickup_name' => $booking->pickup_name,
            'pickup_phone' => $booking->pickup_phone,
            'pickup_scheduled_at' => $booking->pickup_scheduled_at?->toIso8601String(),
            'pickup_scheduled_display' => $booking->pickup_scheduled_at?->format('M d, Y'),
            'submitted_at' => $booking->created_at?->toIso8601String(),
            'submitted_date' => $booking->created_at?->format('M d, Y'),
            'submitted_time' => $booking->created_at?->format('H:i').' GMT',
            'submitted_at_display' => $booking->created_at?->format('M d, Y | h:i A'),
            'completed_at' => $booking->completed_at?->toIso8601String(),
        ];

        if ($includeDeliveryOtp) {
            $payload['delivery_otp'] = $booking->delivery_otp;
        }

        if ($detailed) {
            $payload['documents'] = self::documents($booking);
            $payload['tracking'] = self::tracking($booking);
        }

        return $payload;
    }

    /**
     * @param  Collection<int, OldGoldBooking>  $bookings
     */
    public static function collection(Collection $bookings, bool $detailed = false): array
    {
        return $bookings
            ->map(fn (OldGoldBooking $booking) => self::make($booking, $detailed))
            ->values()
            ->all();
    }

    /**
     * @return list<array{key: string, label: string, url: ?string, uploaded: bool}>
     */
    public static function documents(OldGoldBooking $booking): array
    {
        $stored = is_array($booking->documents) ? $booking->documents : [];
        $types = config('sell_jewellery.document_types', []);

        return collect($types)
            ->map(function (array $meta, string $key) use ($stored, $booking): array {
                $path = $stored[$key] ?? null;

                return [
                    'key' => $key,
                    'label' => $meta['label'],
                    'uploaded' => filled($path),
                    'url' => AssetUrl::publicStorage($path),
                    'required' => (bool) ($meta['required'] ?? false),
                    'required_for' => $meta['required_for'] ?? null,
                    'required_for_identity_owner' => $meta['required_for_identity_owner'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{key: string, label: string, completed: bool, current: bool, completed_at: ?string}>
     */
    public static function tracking(OldGoldBooking $booking): array
    {
        $status = self::normalizeStatus($booking->status);

        if (in_array($status, ['cancelled', 'failed'], true)) {
            return [
                [
                    'key' => $status,
                    'label' => self::statusLabel($status),
                    'completed' => true,
                    'current' => true,
                    'completed_at' => $booking->updated_at?->toIso8601String(),
                ],
            ];
        }

        $steps = config('sell_jewellery.tracking_steps', []);
        $currentIndex = (int) (config('sell_jewellery.status_tracking_index.'.$status) ?? 0);

        return collect($steps)
            ->values()
            ->map(function (array $step, int $index) use ($booking, $currentIndex): array {
                $completed = $index <= $currentIndex;
                $current = $index === $currentIndex;

                return [
                    'key' => $step['key'],
                    'label' => $step['label'],
                    'completed' => $completed,
                    'current' => $current,
                    'completed_at' => $completed
                        ? self::trackingTimestamp($booking, $step['key'])
                        : null,
                ];
            })
            ->all();
    }

    protected static function trackingTimestamp(OldGoldBooking $booking, string $stepKey): ?string
    {
        $at = match ($stepKey) {
            'pending' => $booking->created_at,
            'accepted' => $booking->accepted_at ?? $booking->updated_at,
            'pickup_scheduling' => $booking->pickup_scheduled_at ?? $booking->accepted_at ?? $booking->updated_at,
            'picked_up' => $booking->picked_up_at ?? $booking->pickup_scheduled_at ?? $booking->updated_at,
            'completed' => $booking->completed_at ?? $booking->picked_up_at ?? $booking->updated_at,
            default => null,
        };

        return $at?->toIso8601String();
    }

    public static function normalizeStatus(?string $status): string
    {
        return match ($status) {
            'processing' => 'accepted',
            default => (string) $status,
        };
    }

    public static function statusLabel(?string $status): string
    {
        $normalized = self::normalizeStatus($status);

        return config('sell_jewellery.statuses.'.$normalized, Str::headline(str_replace('_', ' ', (string) $status)));
    }

    public static function metalLabel(?string $metalType): string
    {
        $match = collect(config('sell_jewellery.metal_types', []))
            ->firstWhere('value', $metalType);

        return $match['label'] ?? Str::headline((string) $metalType);
    }

    public static function identityOwnerLabel(?string $value): string
    {
        $match = collect(config('sell_jewellery.identity_owners', []))
            ->firstWhere('value', $value);

        return $match['label'] ?? Str::headline(str_replace('_', ' ', (string) $value));
    }

    public static function sellLocationLabel(?string $value): string
    {
        $match = collect(config('sell_jewellery.sell_locations', []))
            ->firstWhere('value', $value);

        return $match['label'] ?? Str::headline(str_replace('_', ' ', (string) $value));
    }

    protected static function defaultItemName(OldGoldBooking $booking): string
    {
        $parts = array_filter([
            self::metalLabel($booking->metal_type),
            $booking->purity,
            $booking->estimated_weight_grams !== null
                ? rtrim(rtrim(number_format((float) $booking->estimated_weight_grams, 3, '.', ''), '0'), '.').'gm'
                : null,
        ]);

        return $parts !== [] ? implode(' | ', $parts) : 'Old Jewellery';
    }
}
