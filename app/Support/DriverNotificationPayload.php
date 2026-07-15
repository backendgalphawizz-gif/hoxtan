<?php

namespace App\Support;

use App\Models\DriverNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class DriverNotificationPayload
{
    /**
     * @return array{
     *     id: int,
     *     title: string,
     *     body: string,
     *     type: ?string,
     *     category: string,
     *     category_label: string,
     *     is_read: bool,
     *     data: array|object,
     *     read_at: ?string,
     *     created_at: ?string,
     *     created_at_display: ?string,
     *     relative_time: ?string,
     *     section: string
     * }
     */
    public static function make(DriverNotification $notification): array
    {
        $createdAt = $notification->created_at;

        return [
            'id' => $notification->id,
            'title' => $notification->title,
            'body' => $notification->body,
            'type' => $notification->type,
            'category' => self::categoryKey($notification->type),
            'category_label' => self::categoryLabel($notification->type),
            'is_read' => $notification->read_at !== null,
            'data' => $notification->data ?? (object) [],
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $createdAt?->toIso8601String(),
            'created_at_display' => $createdAt ? self::displayTimestamp($createdAt) : null,
            'relative_time' => $createdAt ? self::relativeTime($createdAt) : null,
            'section' => self::section($createdAt),
        ];
    }

    public static function categoryKey(?string $type): string
    {
        return match ($type) {
            'new_assigned_order', 'driver_delivery_assigned', 'driver_pickup_assigned' => 'new_assigned_order',
            'delivery_update' => 'delivery_update',
            'pickup_reminder' => 'pickup_reminder',
            default => $type ? Str::snake($type) : 'general',
        };
    }

    public static function categoryLabel(?string $type): string
    {
        return match (self::categoryKey($type)) {
            'new_assigned_order' => 'NEW ASSIGNED ORDER',
            'delivery_update' => 'DELIVERY UPDATE',
            'pickup_reminder' => 'PICKUP REMINDER',
            default => Str::upper(str_replace('_', ' ', (string) ($type ?? 'GENERAL'))),
        };
    }

    public static function section(?Carbon $createdAt): string
    {
        if (! $createdAt) {
            return 'recent';
        }

        return $createdAt->isToday() ? 'today' : 'recent';
    }

    public static function displayTimestamp(Carbon $at): string
    {
        if ($at->isToday()) {
            return $at->diffForHumans();
        }

        if ($at->isYesterday()) {
            return 'Yesterday, '.$at->format('g:i A');
        }

        return $at->format('D, g:i A');
    }

    public static function relativeTime(Carbon $at): string
    {
        return $at->diffForHumans();
    }
}
