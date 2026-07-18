<?php

namespace App\Filament\Resources\PushNotificationResource\Pages;

use App\Filament\Resources\PushNotificationResource;
use App\Services\PushNotificationDispatchService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewPushNotification extends ViewRecord
{
    protected static string $resource = PushNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn () => $this->record->status !== 'sent'),
            Actions\Action::make('send')
                ->label('Send Now')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->visible(fn () => in_array($this->record->status, ['draft', 'scheduled']))
                ->requiresConfirmation()
                ->action(function (PushNotificationDispatchService $dispatch): void {
                    $result = $dispatch->dispatch($this->record);
                    $feedback = \App\Support\PushDispatchFeedback::fromResult($result);

                    Notification::make()
                        ->title($feedback['title'])
                        ->body($feedback['body'])
                        ->{$feedback['success'] ? 'success' : 'warning'}()
                        ->send();

                    $this->refreshFormData(['status', 'sent_at', 'recipients_count']);
                }),
        ];
    }
}
