<?php

namespace App\Filament\Resources\RedemptionResource\Pages;

use App\Filament\Resources\RedemptionResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewRedemption extends ViewRecord
{
    protected static string $resource = RedemptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check')
                ->color('success')
                ->visible(fn () => $this->record->status === 'pending')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update([
                        'status' => 'approved',
                        'processed_by' => Auth::guard('admin')->id(),
                    ]);
                    Notification::make()->title('Redemption approved')->success()->send();
                }),
            Actions\Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->visible(fn () => in_array($this->record->status, ['pending', 'approved', 'processing']))
                ->form([
                    Forms\Components\Textarea::make('rejection_reason')
                        ->required()
                        ->maxLength(1000),
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'status' => 'rejected',
                        'rejection_reason' => $data['rejection_reason'],
                        'processed_by' => Auth::guard('admin')->id(),
                    ]);
                    Notification::make()->title('Redemption rejected')->danger()->send();
                }),
            Actions\Action::make('dispatch')
                ->label('Dispatch')
                ->icon('heroicon-o-truck')
                ->color('info')
                ->visible(fn () => in_array($this->record->status, ['approved', 'processing']))
                ->form([
                    Forms\Components\TextInput::make('courier_name')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('tracking_number')
                        ->required()
                        ->maxLength(255),
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'status' => 'dispatched',
                        'courier_name' => $data['courier_name'],
                        'tracking_number' => $data['tracking_number'],
                        'dispatched_at' => now(),
                        'processed_by' => Auth::guard('admin')->id(),
                    ]);
                    Notification::make()->title('Redemption dispatched')->success()->send();
                }),
            Actions\Action::make('deliver')
                ->label('Deliver')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn () => $this->record->status === 'dispatched')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update([
                        'status' => 'delivered',
                        'delivered_at' => now(),
                        'processed_by' => Auth::guard('admin')->id(),
                    ]);
                    Notification::make()->title('Redemption marked as delivered')->success()->send();
                }),
        ];
    }
}
