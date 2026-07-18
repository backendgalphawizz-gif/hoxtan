<?php

namespace App\Filament\Resources\PushNotificationResource\Pages;

use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Resources\PushNotificationResource;
use App\Services\PushNotificationDispatchService;
use Filament\Actions;
use Filament\Notifications\Notification;

class EditPushNotification extends BaseEditRecord
{
    protected static string $resource = PushNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->status !== 'sent'),
            Actions\Action::make('send')
                ->label('Send Now')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->visible(fn () => in_array($this->record->status, ['draft', 'scheduled']))
                ->requiresConfirmation()
                ->action(function (PushNotificationDispatchService $dispatch): void {
                    $count = $dispatch->dispatch($this->record);

                    Notification::make()
                        ->title('Push notification sent')
                        ->body('Delivered to '.$count.' recipient(s).')
                        ->success()
                        ->send();

                    $this->refreshFormData(['status', 'sent_at', 'recipients_count']);
                }),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! in_array($data['target'] ?? null, ['specific', 'specific_drivers'], true)) {
            $data['target_user_ids'] = null;
        }

        if (($data['status'] ?? null) === 'sent' && $this->record->status !== 'sent') {
            $data['status'] = $this->record->status;
            $this->shouldDispatchAfterSave = true;
        }

        return $data;
    }

    protected bool $shouldDispatchAfterSave = false;

    protected function afterSave(): void
    {
        if (! $this->shouldDispatchAfterSave) {
            return;
        }

        $count = app(PushNotificationDispatchService::class)->dispatch($this->record->fresh());

        Notification::make()
            ->title('Push notification sent')
            ->body('Delivered to '.$count.' recipient(s).')
            ->success()
            ->send();
    }
}
