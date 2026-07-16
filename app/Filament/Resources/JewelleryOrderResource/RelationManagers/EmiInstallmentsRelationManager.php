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
            ->headerActions([
                Tables\Actions\Action::make('mark_all_paid')
                    ->label('Mark All Remaining Paid')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(function (): bool {
                        $order = $this->getOwnerRecord();

                        return $order->payment_mode === 'emi'
                            && ! in_array($order->status, ['cancelled', 'failed'], true)
                            && $order->emiInstallments()->where('status', 'pending')->exists();
                    })
                    ->form([
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes (optional)')
                            ->rows(2),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Mark all remaining EMIs as paid')
                    ->modalDescription('This will mark every unpaid monthly EMI for this order as paid and unlock delivery if complete.')
                    ->action(function (array $data): void {
                        $order = $this->getOwnerRecord();

                        $result = app(JewelleryEmiService::class)->markAllPendingPaid(
                            $order,
                            auth('admin')->id(),
                            $data['notes'] ?? null,
                        );

                        Notification::make()
                            ->title('Remaining EMIs marked as paid')
                            ->body($result['paid_count'] > 0
                                ? $result['paid_count'].' installment(s) paid. '.$result['order']->emiProgressLabel()
                                : 'No pending EMIs found.')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('mark_paid')
                    ->label('Mark Paid')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(function (JewelleryOrderEmiInstallment $record): bool {
                        $order = $this->getOwnerRecord();

                        return $record->isPending()
                            && ! in_array($order->status, ['cancelled', 'failed'], true);
                    })
                    ->form([
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes (optional)')
                            ->rows(2),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Mark EMI as Paid')
                    ->modalDescription('Confirm this monthly EMI installment has been paid.')
                    ->action(function (JewelleryOrderEmiInstallment $record, array $data): void {
                        $order = $this->getOwnerRecord();

                        if (in_array($order->status, ['cancelled', 'failed'], true)) {
                            Notification::make()
                                ->title('Cannot mark EMI paid')
                                ->body('This EMI order is cancelled.')
                                ->danger()
                                ->send();

                            return;
                        }

                        app(JewelleryEmiService::class)->markInstallmentPaid(
                            $record,
                            auth('admin')->id(),
                            $data['notes'] ?? null,
                        );

                        $order = $order->fresh('emiInstallments');

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
                    ->visible(function (JewelleryOrderEmiInstallment $record): bool {
                        $order = $this->getOwnerRecord();

                        return $record->isPaid()
                            && ! in_array($order->status, ['cancelled', 'failed'], true);
                    })
                    ->requiresConfirmation()
                    ->action(function (JewelleryOrderEmiInstallment $record): void {
                        $order = $this->getOwnerRecord();

                        if (in_array($order->status, ['cancelled', 'failed'], true)) {
                            Notification::make()
                                ->title('Cannot change EMI status')
                                ->body('This EMI order is cancelled.')
                                ->danger()
                                ->send();

                            return;
                        }

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
