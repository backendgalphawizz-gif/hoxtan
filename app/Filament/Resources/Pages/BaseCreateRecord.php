<?php

namespace App\Filament\Resources\Pages;

use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord as FilamentCreateRecord;

abstract class BaseCreateRecord extends FilamentCreateRecord
{
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        $label = static::getResource()::getModelLabel();

        return Notification::make()
            ->success()
            ->title(ucfirst($label).' created')
            ->body('The record was saved successfully.');
    }
}
