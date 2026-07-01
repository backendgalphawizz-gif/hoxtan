<?php

namespace App\Filament\Resources\PushNotificationResource\Pages;

use App\Filament\Resources\PushNotificationResource;
use App\Filament\Resources\Pages\BaseCreateRecord;
use Illuminate\Support\Facades\Auth;

class CreatePushNotification extends BaseCreateRecord
{
    protected static string $resource = PushNotificationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::guard('admin')->id();

        if ($data['target'] !== 'specific') {
            $data['target_user_ids'] = null;
        }

        return $data;
    }
}
