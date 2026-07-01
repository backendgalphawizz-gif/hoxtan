<?php

namespace App\Services;

use App\Models\PushNotification;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;

class PushNotificationDispatchService
{
    public function dispatch(PushNotification $notification): int
    {
        $users = $this->resolveRecipients($notification);
        $count = 0;

        DB::transaction(function () use ($notification, $users, &$count): void {
            foreach ($users as $user) {
                UserNotification::create([
                    'user_id' => $user->id,
                    'push_notification_id' => $notification->id,
                    'title' => $notification->title,
                    'body' => $notification->body,
                ]);

                $count++;
            }

            $notification->update([
                'status' => 'sent',
                'sent_at' => now(),
                'recipients_count' => $count,
            ]);
        });

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
}
