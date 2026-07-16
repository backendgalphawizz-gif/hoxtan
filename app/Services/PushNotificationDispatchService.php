<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\PushNotification;
use App\Models\User;

class PushNotificationDispatchService
{
    public function __construct(
        private readonly NotificationInboxService $inbox,
    ) {}

    public function dispatch(PushNotification $notification): int
    {
        $target = (string) $notification->target;

        if (in_array($target, ['all_drivers', 'specific_drivers'], true)) {
            $drivers = $this->resolveDriverRecipients($notification);
            $count = $this->inbox->notifyDrivers(
                $drivers,
                $notification->title,
                $notification->body,
                'broadcast',
                [
                    'push_notification_id' => (string) $notification->id,
                ],
                push: true,
            );
        } else {
            $users = $this->resolveRecipients($notification);
            $count = $this->inbox->notifyUsers(
                $users,
                $notification->title,
                $notification->body,
                'broadcast',
                [
                    'push_notification_id' => (string) $notification->id,
                ],
                push: true,
                pushNotificationId: $notification->id,
            );
        }

        $notification->update([
            'status' => 'sent',
            'sent_at' => now(),
            'recipients_count' => $count,
        ]);

        return $count;
    }

    public function resolveRecipients(PushNotification $notification)
    {
        return match ($notification->target) {
            'investors' => User::query()
                ->where('is_blocked', false)
                ->where(function ($query): void {
                    $query->where('role', 'investor')
                        ->orWhere('gold_holdings', '>', 0)
                        ->orWhere('silver_holdings', '>', 0);
                })
                ->get(),
            'specific' => User::query()
                ->whereIn('id', $notification->target_user_ids ?? [])
                ->where('is_blocked', false)
                ->get(),
            default => User::query()->where('is_blocked', false)->get(),
        };
    }

    public function resolveDriverRecipients(PushNotification $notification)
    {
        return match ($notification->target) {
            'specific_drivers' => Driver::query()
                ->whereIn('id', $notification->target_user_ids ?? [])
                ->where('is_active', true)
                ->get(),
            default => Driver::query()->where('is_active', true)->get(),
        };
    }
}
