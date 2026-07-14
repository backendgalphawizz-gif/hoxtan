<?php

namespace App\Services;

use App\Models\PushNotification;

class PushNotificationDispatchService
{
    public function __construct(
        private readonly NotificationInboxService $inbox,
    ) {}

    public function dispatch(PushNotification $notification): int
    {
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
            'investors' => \App\Models\User::query()
                ->where('is_blocked', false)
                ->where(function ($query): void {
                    $query->where('role', 'investor')
                        ->orWhere('gold_holdings', '>', 0)
                        ->orWhere('silver_holdings', '>', 0);
                })
                ->get(),
            'specific' => \App\Models\User::query()
                ->whereIn('id', $notification->target_user_ids ?? [])
                ->where('is_blocked', false)
                ->get(),
            default => \App\Models\User::query()->where('is_blocked', false)->get(),
        };
    }
}
