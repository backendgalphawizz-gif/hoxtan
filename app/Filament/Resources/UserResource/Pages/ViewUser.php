<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('block')
                ->label('Block')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->visible(fn (): bool => ! $this->record->is_blocked)
                ->form([
                    Forms\Components\Textarea::make('block_reason')
                        ->label('Block reason')
                        ->required()
                        ->maxLength(500),
                ])
                ->action(function (array $data): void {
                    /** @var User $user */
                    $user = $this->record;
                    $user->update([
                        'is_blocked' => true,
                        'blocked_at' => now(),
                        'block_reason' => $data['block_reason'],
                    ]);
                    Notification::make()->title('Account blocked')->danger()->send();
                }),
            Actions\Action::make('unblock')
                ->label('Unblock')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => (bool) $this->record->is_blocked)
                ->requiresConfirmation()
                ->modalHeading('Unblock account')
                ->modalDescription('This user will be able to log in and use the app again.')
                ->action(function (): void {
                    /** @var User $user */
                    $user = $this->record;
                    $user->update([
                        'is_blocked' => false,
                        'blocked_at' => null,
                        'block_reason' => null,
                    ]);
                    Notification::make()->title('Account unblocked')->success()->send();
                }),
        ];
    }
}
