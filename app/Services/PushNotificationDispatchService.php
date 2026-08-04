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

    /**
     * @return array{recipients: int, push_tokens: int, push_success: int, push_failure: int, firebase_ready: bool, error: ?string}
     */
    public function dispatch(PushNotification $notification): array
    {
        $target = (string) $notification->target;
        $pushResult = [
            'success' => 0,
            'failure' => 0,
            'tokens' => 0,
            'firebase_ready' => false,
            'error' => null,
        ];

        if (in_array($target, ['all_drivers', 'specific_drivers'], true)) {
            $drivers = $this->resolveDriverRecipients($notification);
            $result = $this->inbox->notifyDrivers(
                $drivers,
                $notification->title,
                $notification->body,
                'broadcast',
                [
                    'push_notification_id' => (string) $notification->id,
                ],
                push: true,
            );
            $count = $result['recipients'];
            $pushResult = $result['push'];
        } else {
            $users = $this->resolveRecipients($notification);
            $result = $this->inbox->notifyUsers(
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
            $count = $result['recipients'];
            $pushResult = $result['push'];
        }

        $notification->update([
            'status' => 'sent',
            'sent_at' => now(),
            'recipients_count' => $count,
        ]);

        return [
            'recipients' => $count,
            'push_tokens' => (int) ($pushResult['tokens'] ?? 0),
            'push_success' => (int) ($pushResult['success'] ?? 0),
            'push_failure' => (int) ($pushResult['failure'] ?? 0),
            'firebase_ready' => (bool) ($pushResult['firebase_ready'] ?? false),
            'error' => $pushResult['error'] ?? null,
        ];
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
