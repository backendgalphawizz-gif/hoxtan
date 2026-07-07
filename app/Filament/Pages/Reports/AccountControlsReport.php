<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Exports\Reports\AccountControlsExporter;
use App\Models\User;
use App\Models\UserRestriction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class AccountControlsReport extends BaseReportPage
{
    protected static function adminPermissionModule(): string
    {
        return 'reports_account_controls';
    }

    protected static ?string $title = 'Account Controls';

    public function getSubheading(): ?string
    {
        return 'Block account, hold withdrawals, restrict wallet/bonus/referral — contact HOXTAN support notes.';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(User::query()->with('restriction'))
            ->columns([
                TextColumn::make('id')->label('Client ID'),
                TextColumn::make('name')->searchable(),
                TextColumn::make('phone')->searchable(),
                TextColumn::make('email')->searchable()->toggleable(),
                IconColumn::make('is_blocked')->boolean()->label('Account Blocked'),
                IconColumn::make('restriction.wallet_blocked')->boolean()->label('Wallet'),
                IconColumn::make('restriction.bonus_blocked')->boolean()->label('Bonus'),
                IconColumn::make('restriction.referral_blocked')->boolean()->label('Referral'),
                IconColumn::make('restriction.withdrawal_hold')->boolean()->label('Withdrawal Hold'),
                TextColumn::make('restriction.support_notes')->limit(30)->placeholder('—'),
            ])
            ->actions([
                Action::make('restrictions')
                    ->label('Restrictions')
                    ->icon('heroicon-o-shield-exclamation')
                    ->visible(fn (): bool => static::canEditReport())
                    ->form([
                        Forms\Components\Toggle::make('wallet_blocked')->label('Block Wallet'),
                        Forms\Components\Toggle::make('bonus_blocked')->label('Block Bonus'),
                        Forms\Components\Toggle::make('referral_blocked')->label('Block Referral'),
                        Forms\Components\Toggle::make('withdrawal_hold')->label('Hold Withdrawals'),
                        Forms\Components\Textarea::make('support_notes')
                            ->label('HOXTAN Support Notes')
                            ->rows(3),
                    ])
                    ->fillForm(fn (User $record): array => [
                        'wallet_blocked' => (bool) $record->restriction?->wallet_blocked,
                        'bonus_blocked' => (bool) $record->restriction?->bonus_blocked,
                        'referral_blocked' => (bool) $record->restriction?->referral_blocked,
                        'withdrawal_hold' => (bool) $record->restriction?->withdrawal_hold,
                        'support_notes' => $record->restriction?->support_notes,
                    ])
                    ->action(function (User $record, array $data): void {
                        UserRestriction::updateOrCreate(
                            ['user_id' => $record->id],
                            [
                                ...$data,
                                'updated_by' => Auth::guard('admin')->id(),
                            ],
                        );

                        Notification::make()->title('Restrictions updated')->success()->send();
                    }),
                Action::make('manage')
                    ->label('Manage User')
                    ->icon('heroicon-o-user')
                    ->url(fn (User $record) => route('filament.admin.resources.users.edit', $record)),
            ])
            ->headerActions([static::reportExportAction(AccountControlsExporter::class)]);
    }

    protected static function canEditReport(): bool
    {
        return \App\Support\AdminPermissions::can(static::adminPermissionModule(), 'edit');
    }
}
