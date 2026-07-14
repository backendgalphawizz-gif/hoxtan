<?php

namespace App\Filament\Resources\MetalWithdrawalResource\Pages;

use App\Filament\Resources\MetalWithdrawalResource;
use App\Models\MetalWithdrawal;
use App\Services\MetalWithdrawalService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewMetalWithdrawal extends ViewRecord
{
    protected static string $resource = MetalWithdrawalResource::class;

    protected function getHeaderActions(): array
    {
        /** @var MetalWithdrawal $record */
        $record = $this->getRecord();

        return [
            Actions\Action::make('approve')
                ->label('Approve Payout')
                ->color('success')
                ->visible(fn (): bool => $record->isPending())
                ->form([
                    Forms\Components\TextInput::make('payout_reference')
                        ->label('Bank UTR / Reference (optional)')
                        ->maxLength(100),
                ])
                ->requiresConfirmation()
                ->action(function (array $data) use ($record): void {
                    app(MetalWithdrawalService::class)->approve(
                        $record,
                        Auth::guard('admin')->id(),
                        $data['payout_reference'] ?? null,
                    );

                    Notification::make()->title('Withdrawal approved')->success()->send();
                    $this->refreshFormData(['status', 'paid_at', 'reviewed_at', 'payout_reference']);
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
                    app(MetalWithdrawalService::class)->reject(
                        $record,
                        (int) Auth::guard('admin')->id(),
                        $data['rejection_reason'],
                    );

                    Notification::make()->title('Withdrawal rejected')->warning()->send();
                    $this->refreshFormData(['status', 'rejection_reason', 'reviewed_at']);
                }),
        ];
    }
}
