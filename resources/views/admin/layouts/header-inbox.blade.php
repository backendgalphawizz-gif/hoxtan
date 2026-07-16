@php
    use App\Filament\Resources\AdminNotificationResource;
    use App\Support\AdminPermissions;
    use App\Support\NavigationBadgeCounts;

    $canViewInbox = AdminPermissions::canViewModule('admin_notifications');
    $unreadCount = $canViewInbox ? NavigationBadgeCounts::unreadAdminNotifications() : 0;
    $inboxUrl = AdminNotificationResource::getUrl('index');
    $badgeLabel = $unreadCount > 99 ? '99+' : (string) $unreadCount;
@endphp

@if ($canViewInbox)
    <a
        href="{{ $inboxUrl }}"
        class="gs-header-inbox"
        title="Inbox{{ $unreadCount > 0 ? " ({$badgeLabel} unread)" : '' }}"
        aria-label="Inbox{{ $unreadCount > 0 ? ", {$badgeLabel} unread notifications" : '' }}"
    >
        <x-filament::icon
            icon="heroicon-o-inbox"
            class="gs-header-inbox__icon"
        />

        @if ($unreadCount > 0)
            <span class="gs-header-inbox__badge" aria-hidden="true">{{ $badgeLabel }}</span>
        @endif
    </a>
@endif
