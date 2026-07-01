<?php

namespace App\Filament\Resources\PushNotificationResource\Pages;

use App\Filament\Exports\PushNotificationExporter;
use App\Filament\Resources\PushNotificationResource;
use App\Support\FilamentExportActions;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPushNotifications extends ListRecords
{
    protected static string $resource = PushNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            FilamentExportActions::headerExport(PushNotificationExporter::class, 'push_notifications'),
            Actions\CreateAction::make(),
        ];
    }
}
