<?php

namespace App\Filament\Resources\JewelleryEmiRefundRequestResource\Pages;

use App\Filament\Resources\JewelleryEmiRefundRequestResource;
use App\Models\JewelleryEmiRefundRequest;
use App\Services\JewelleryEmiCancellationService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewJewelleryEmiRefundRequest extends ViewRecord
{
    protected static string $resource = JewelleryEmiRefundRequestResource::class;

    protected function getHeaderActions(): array
    {
        /** @var JewelleryEmiRefundRequest $record */
        $record = $this->getRecord();

        return [
            Actions\Action::make('approve')
                ->label('Approve Refund')
                ->color('success')
                ->visible(fn (): bool => $record->isPending())
                ->form([
                    Forms\Components\TextInput::make('refund_reference')
                        ->label('Bank UTR / Reference (optional)')
                        ->maxLength(100),
                ])
                ->requiresConfirmation()
                ->action(function (array $data) use ($record): void {
                    app(JewelleryEmiCancellationService::class)->approve(
                        $record,
                        Auth::guard('admin')->id(),
                        $data['refund_reference'] ?? null,
                    );

                    Notification::make()->title('Refund approved')->success()->send();
                    $this->refreshFormData(['status', 'refunded_at', 'reviewed_at', 'refund_reference']);
                }),
            Actions\Action::make('reject')
                ->label('Reject')
                ->color('danger')
                ->visible(fn (): bool => $record->isPending())
                ->form([
                    Forms\Components\Textarea::make('rejection_reason')
                        ->required()
                        ->maxLength(1000),
                ])
                ->requiresConfirmation()
                ->action(function (array $data) use ($record): void {
                    app(JewelleryEmiCancellationService::class)->reject(
                        $record,
                        (int) Auth::guard('admin')->id(),
                        $data['rejection_reason'],
                    );

                    Notification::make()->title('Refund rejected')->warning()->send();
                    $this->refreshFormData(['status', 'rejection_reason', 'reviewed_at']);
                }),
        ];
    }
}
