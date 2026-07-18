<?php

namespace App\Filament\Resources\PushNotificationResource\Pages;

use App\Filament\Resources\Pages\BaseCreateRecord;
use App\Filament\Resources\PushNotificationResource;
use App\Services\PushNotificationDispatchService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class CreatePushNotification extends BaseCreateRecord
{
    protected static string $resource = PushNotificationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::guard('admin')->id();

        // Keep selected IDs for specific users/drivers; clear for broadcast targets.
        if (! in_array($data['target'] ?? null, ['specific', 'specific_drivers'], true)) {
            $data['target_user_ids'] = null;
        }

        // Status "sent" is applied after dispatch — save as draft first if chosen.
        if (($data['status'] ?? null) === 'sent') {
            $data['status'] = 'draft';
            $this->shouldDispatchAfterCreate = true;
        }

        return $data;
    }

    protected bool $shouldDispatchAfterCreate = false;

    protected function afterCreate(): void
    {
        if (! $this->shouldDispatchAfterCreate) {
            return;
        }

        $count = app(PushNotificationDispatchService::class)->dispatch($this->record);
        $feedback = \App\Support\PushDispatchFeedback::fromResult($count);

        Notification::make()
            ->title($feedback['title'])
            ->body($feedback['body'])
            ->{$feedback['success'] ? 'success' : 'warning'}()
            ->send();
    }
}
