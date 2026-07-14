<?php

namespace App\Filament\Resources\AdminNotificationResource\Pages;

use App\Filament\Resources\AdminNotificationResource;
use App\Models\AdminNotification;
use App\Support\NavigationBadgeCounts;
use Filament\Resources\Pages\ViewRecord;

class ViewAdminNotification extends ViewRecord
{
    protected static string $resource = AdminNotificationResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        if ($record instanceof AdminNotification && $record->read_at === null) {
            $record->markRead();
            NavigationBadgeCounts::forgetUnreadAdminNotifications();
        }

        return $data;
    }
}
