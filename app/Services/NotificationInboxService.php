<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\AdminNotification;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\NavigationBadgeCounts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NotificationInboxService
{
    public function __construct(
        private readonly FirebaseCloudMessagingService $fcm,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function notifyUser(
        User $user,
        string $title,
        string $body,
        ?string $type = null,
        array $data = [],
        bool $push = true,
        ?int $pushNotificationId = null,
    ): UserNotification {
        $notification = UserNotification::create([
            'user_id' => $user->id,
            'push_notification_id' => $pushNotificationId,
            'title' => $title,
            'body' => $body,
            'type' => $type,
            'data' => $data === [] ? null : $data,
        ]);

        if ($push) {
            $this->fcm->sendToOwners([$user], $title, $body, $data, $type);
        }

        return $notification;
    }

    /**
     * @param  Collection<int, User>|iterable<User>  $users
     * @param  array<string, mixed>  $data
     */
    public function notifyUsers(
        iterable $users,
        string $title,
        string $body,
        ?string $type = null,
        array $data = [],
        bool $push = true,
        ?int $pushNotificationId = null,
    ): int {
        $users = collect($users)->unique('id')->values();
        $count = 0;

        DB::transaction(function () use ($users, $title, $body, $type, $data, $pushNotificationId, &$count): void {
            foreach ($users as $user) {
                UserNotification::create([
                    'user_id' => $user->id,
                    'push_notification_id' => $pushNotificationId,
                    'title' => $title,
                    'body' => $body,
                    'type' => $type,
                    'data' => $data === [] ? null : $data,
                ]);
                $count++;
            }
        });

        if ($push && $count > 0) {
            $this->fcm->sendToOwners($users, $title, $body, $data, $type);
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  Collection<int, Admin>|iterable<Admin>|null  $admins  null = all active admins
     */
    public function notifyAdmins(
        string $title,
        string $body,
        ?string $type = null,
        array $data = [],
        ?iterable $admins = null,
        bool $push = true,
    ): int {
        $recipients = $admins === null
            ? Admin::query()->where('is_active', true)->get()
            : collect($admins)->unique('id')->values();

        $count = 0;

        DB::transaction(function () use ($recipients, $title, $body, $type, $data, &$count): void {
            foreach ($recipients as $admin) {
                AdminNotification::create([
                    'admin_id' => $admin->id,
                    'title' => $title,
                    'body' => $body,
                    'type' => $type,
                    'data' => $data === [] ? null : $data,
                ]);
                $count++;
            }
        });

        if ($push && $count > 0) {
            $this->fcm->sendToOwners($recipients, $title, $body, $data, $type);
        }

        foreach ($recipients as $admin) {
            NavigationBadgeCounts::forgetUnreadAdminNotifications((int) $admin->id);
        }

        return $count;
    }

    public function unreadCountFor(Model $owner): int
    {
        if ($owner instanceof User) {
            return UserNotification::query()
                ->where('user_id', $owner->id)
                ->whereNull('read_at')
                ->count();
        }

        if ($owner instanceof Admin) {
            return AdminNotification::query()
                ->where('admin_id', $owner->id)
                ->whereNull('read_at')
                ->count();
        }

        return 0;
    }

    public function listForUser(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return UserNotification::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->paginate($perPage);
    }

    public function listForAdmin(Admin $admin, int $perPage = 20): LengthAwarePaginator
    {
        return AdminNotification::query()
            ->where('admin_id', $admin->id)
            ->latest('id')
            ->paginate($perPage);
    }

    public function markUserNotificationRead(User $user, int $id): ?UserNotification
    {
        $notification = UserNotification::query()
            ->where('user_id', $user->id)
            ->whereKey($id)
            ->first();

        $notification?->markRead();

        return $notification;
    }

    public function markAdminNotificationRead(Admin $admin, int $id): ?AdminNotification
    {
        $notification = AdminNotification::query()
            ->where('admin_id', $admin->id)
            ->whereKey($id)
            ->first();

        $notification?->markRead();

        return $notification;
    }

    public function markAllReadFor(Model $owner): int
    {
        if ($owner instanceof User) {
            return UserNotification::query()
                ->where('user_id', $owner->id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        if ($owner instanceof Admin) {
            return AdminNotification::query()
                ->where('admin_id', $owner->id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        return 0;
    }
}
