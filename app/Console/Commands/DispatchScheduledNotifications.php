<?php

namespace App\Console\Commands;

use App\Models\PushNotification;
use App\Services\PushNotificationDispatchService;
use Illuminate\Console\Command;

class DispatchScheduledNotifications extends Command
{
    protected $signature = 'notifications:dispatch-scheduled';

    protected $description = 'Send push notifications that are scheduled for now or earlier';

    public function handle(PushNotificationDispatchService $dispatch): int
    {
        $notifications = PushNotification::query()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($notifications as $notification) {
            $count = $dispatch->dispatch($notification);
            $this->info("Dispatched \"{$notification->title}\" to {$count} users.");
        }

        return self::SUCCESS;
    }
}
