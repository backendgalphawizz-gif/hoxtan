<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use App\Services\FirebaseCloudMessagingService;
use App\Services\NotificationInboxService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    public function registerDevice(Request $request, FirebaseCloudMessagingService $fcm): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['nullable', 'string', Rule::in(['android', 'ios', 'web'])],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $device = $fcm->registerToken(
            $request->user(),
            $data['token'],
            $data['platform'] ?? null,
            $data['device_name'] ?? null,
        );

        return ApiResponse::success([
            'device_token' => [
                'id' => $device->id,
                'platform' => $device->platform,
                'device_name' => $device->device_name,
                'updated_at' => optional($device->updated_at)?->toIso8601String(),
            ],
        ], 'Device token registered.');
    }

    public function removeDevice(Request $request, FirebaseCloudMessagingService $fcm): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        $fcm->removeToken($request->user(), $data['token']);

        return ApiResponse::success([], 'Device token removed.');
    }

    public function index(Request $request, NotificationInboxService $inbox): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 50);
        $paginator = $inbox->listForUser($request->user(), $perPage);

        return ApiResponse::success([
            'unread_count' => $inbox->unreadCountFor($request->user()),
            'notifications' => collect($paginator->items())->map(fn (UserNotification $n) => $this->serialize($n))->values(),
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
        return ApiResponse::success([
            'unread_count' => $inbox->unreadCountFor($request->user()),
        ]);
    }

    public function markRead(Request $request, int $notification, NotificationInboxService $inbox): JsonResponse
    {
        $record = $inbox->markUserNotificationRead($request->user(), $notification);

        if ($record === null) {
            return ApiResponse::error('Notification not found.', [], 404);
        }

        return ApiResponse::success([
            'notification' => $this->serialize($record->fresh()),
            'unread_count' => $inbox->unreadCountFor($request->user()),
        ], 'Notification marked as read.');
    }

    public function markAllRead(Request $request, NotificationInboxService $inbox): JsonResponse
    {
        $updated = $inbox->markAllReadFor($request->user());

        return ApiResponse::success([
            'updated' => $updated,
            'unread_count' => 0,
        ], 'All notifications marked as read.');
    }

    private function serialize(UserNotification $n): array
    {
        return [
            'id' => $n->id,
            'title' => $n->title,
            'body' => $n->body,
            'type' => $n->type,
            'data' => $n->data ?? (object) [],
            'is_read' => $n->read_at !== null,
            'read_at' => optional($n->read_at)?->toIso8601String(),
            'created_at' => optional($n->created_at)?->toIso8601String(),
        ];
    }
}
