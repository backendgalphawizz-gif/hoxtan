<?php

namespace App\Filament\Resources\JewelleryOrderResource\RelationManagers;

use App\Models\JewelleryOrderEmiInstallment;
use App\Services\JewelleryEmiService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class EmiInstallmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'emiInstallments';

    protected static ?string $title = 'Monthly EMI Status';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->payment_mode === 'emi';
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('installment_number')
            ->columns([
                Tables\Columns\TextColumn::make('installment_number')
                    ->label('EMI #')
                    ->formatStateUsing(fn ($state): string => 'Month '.$state)
                    ->sortable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->inr(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'paid',
                    ])
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Paid At')
                    ->dateTime('d M Y, h:i A')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('markedPaidByAdmin.name')
                    ->label('Marked By')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('notes')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('mark_paid')
                    ->label('Mark Paid')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (JewelleryOrderEmiInstallment $record): bool => $record->isPending())
                    ->form([
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes (optional)')
                            ->rows(2),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Mark EMI as Paid')
                    ->modalDescription('Confirm this monthly EMI installment has been paid.')
                    ->action(function (JewelleryOrderEmiInstallment $record, array $data): void {
                        app(JewelleryEmiService::class)->markInstallmentPaid(
                            $record,
                            auth('admin')->id(),
                            $data['notes'] ?? null,
                        );

                        $order = $this->getOwnerRecord()->fresh('emiInstallments');

                        Notification::make()
                            ->title('EMI marked as paid')
                            ->body($order->isDeliveryEligible()
                                ? 'All EMIs paid. Delivery is now unlocked — you can assign a driver.'
                                : 'Progress: '.$order->emiProgressLabel())
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('mark_pending')
                    ->label('Mark Pending')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (JewelleryOrderEmiInstallment $record): bool => $record->isPaid())
                    ->requiresConfirmation()
                    ->action(function (JewelleryOrderEmiInstallment $record): void {
                        app(JewelleryEmiService::class)->markInstallmentPending($record);

                        Notification::make()
                            ->title('EMI set back to pending')
                            ->warning()
                            ->send();
                    }),
            ])
            ->defaultSort('installment_number')
            ->paginated(false);
    }
}
