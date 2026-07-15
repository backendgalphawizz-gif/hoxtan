<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverNotification;
use App\Services\NotificationInboxService;
use App\Support\ApiResponse;
use App\Support\DriverNotificationPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverNotificationController extends Controller
{
    public function index(Request $request, NotificationInboxService $inbox): JsonResponse
    {
        /** @var Driver $driver */
        $driver = $request->user();
        $perPage = min(max((int) $request->query('per_page', 20), 1), 50);
        $paginator = $inbox->listForDriver($driver, $perPage);

        $notifications = collect($paginator->items())
            ->map(fn (DriverNotification $n) => DriverNotificationPayload::make($n))
            ->values();

        $grouped = [
            [
                'key' => 'today',
                'label' => 'TODAY',
                'notifications' => $notifications->where('section', 'today')->values()->all(),
            ],
            [
                'key' => 'recent',
                'label' => 'RECENT',
                'notifications' => $notifications->where('section', 'recent')->values()->all(),
            ],
        ];

        return ApiResponse::success([
            'unread_count' => $inbox->unreadCountFor($driver),
            'notifications' => $notifications->all(),
            'sections' => $grouped,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function unreadCount(Request $request, NotificationInboxService $inbox): JsonResponse
    {
        /** @var Driver $driver */
        $driver = $request->user();

        return ApiResponse::success([
            'unread_count' => $inbox->unreadCountFor($driver),
        ]);
    }

    public function markRead(Request $request, int $notification, NotificationInboxService $inbox): JsonResponse
    {
        /** @var Driver $driver */
        $driver = $request->user();
        $record = $inbox->markDriverNotificationRead($driver, $notification);

        if ($record === null) {
            return ApiResponse::error('Notification not found.', [], 404);
        }

        return ApiResponse::success([
            'notification' => DriverNotificationPayload::make($record->fresh()),
            'unread_count' => $inbox->unreadCountFor($driver),
        ], 'Notification marked as read.');
    }

    public function markAllRead(Request $request, NotificationInboxService $inbox): JsonResponse
    {
        /** @var Driver $driver */
        $driver = $request->user();
        $updated = $inbox->markAllReadFor($driver);

        return ApiResponse::success([
            'updated' => $updated,
            'unread_count' => 0,
        ], 'All notifications marked as read.');
    }
}
