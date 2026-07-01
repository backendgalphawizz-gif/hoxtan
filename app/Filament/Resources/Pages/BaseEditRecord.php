<?php

namespace App\Filament\Resources\Pages;

use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord as FilamentEditRecord;

abstract class BaseEditRecord extends FilamentEditRecord
{
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        $label = static::getResource()::getModelLabel();

        return Notification::make()
            ->success()
            ->title(ucfirst($label).' updated')
            ->body('Your changes were saved successfully.');
    }
}
