<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Filament\Resources\MetalWithdrawalResource\Pages;
use App\Models\MetalWithdrawal;
use App\Services\MetalWithdrawalService;
use App\Support\FilamentTableActions;
use App\Support\NavigationBadgeCounts;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class MetalWithdrawalResource extends Resource
{
    use InteractsWithAdminPermissions;

    protected static function adminPermissionModule(): string
    {
        return 'metal_withdrawals';
    }

    protected static ?string $model = MetalWithdrawal::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Investment Management';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Metal Withdrawals';

    protected static ?string $modelLabel = 'Metal Withdrawal';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user', 'reviewer', 'sigPlan']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Request')
                    ->schema([
                        Forms\Components\TextInput::make('reference_id')->disabled(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'approved' => 'Approved',
                                'paid' => 'Paid',
                                'rejected' => 'Rejected',
                                'cancelled' => 'Cancelled',
                            ])
                            ->disabled(),
                        Forms\Components\TextInput::make('user.name')
                            ->label('Customer')
                            ->disabled(),
                        Forms\Components\Select::make('asset_source')
                            ->options([
                                'gold' => 'Gold',
                                'silver' => 'Silver',
                                'sig' => 'SIG',
                            ])
                            ->disabled(),
                        Forms\Components\TextInput::make('metal_type')->disabled(),
                        Forms\Components\TextInput::make('input_mode')->disabled(),
                        Forms\Components\DateTimePicker::make('requested_at')->disabled()->native(false),
                        Forms\Components\DateTimePicker::make('auto_approve_at')
                            ->label('Auto Approve At')
                            ->disabled()
                            ->native(false),
                    ])->columns(2),
                Forms\Components\Section::make('Amount')
                    ->schema([
                        Forms\Components\TextInput::make('quantity_grams')
                            ->label('Weight (g)')
                            ->disabled(),
                        Forms\Components\TextInput::make('rate_per_gram')
                            ->label('Rate / g')
                            ->prefix('₹')
                            ->disabled(),
                        Forms\Components\TextInput::make('amount')
                            ->label('Payout Amount')
                            ->prefix('₹')
                            ->disabled(),
                    ])->columns(3),
                Forms\Components\Section::make('Bank Account')
                    ->schema([
                        Forms\Components\TextInput::make('bank_name')->disabled(),
                        Forms\Components\TextInput::make('account_holder_name')->disabled(),
                        Forms\Components\TextInput::make('account_number')->disabled(),
                        Forms\Components\TextInput::make('ifsc_code')->disabled(),
                        Forms\Components\TextInput::make('payout_reference')
                            ->label('Bank UTR / Reference')
                            ->disabled(fn (?MetalWithdrawal $record): bool => ! $record?->isPending()),
                        Forms\Components\Textarea::make('rejection_reason')
                            ->disabled(fn (?MetalWithdrawal $record): bool => ! $record?->isPending())
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference_id')
                    ->label('Request ID')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable(),
                Tables\Columns\TextColumn::make('asset_source')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => strtoupper($state)),
                Tables\Columns\TextColumn::make('quantity_grams')
                    ->label('Grams')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 4).' g'),
                Tables\Columns\TextColumn::make('amount')->inr()->label('Amount')->weight('bold'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'success' => fn ($state) => in_array($state, ['approved', 'paid'], true),
                        'danger' => 'rejected',
                        'gray' => 'cancelled',
                    ])
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                Tables\Columns\TextColumn::make('auto_approve_at')
                    ->label('Auto Approve')
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),
                Tables\Columns\TextColumn::make('requested_at')
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),
            ])
            ->defaultSort('requested_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'paid' => 'Paid',
                        'rejected' => 'Rejected',
                    ]),
                Tables\Filters\SelectFilter::make('asset_source')
                    ->options([
                        'gold' => 'Gold',
                        'silver' => 'Silver',
                        'sig' => 'SIG',
                    ]),
            ])
            ->actions([
                FilamentTableActions::view(),
                FilamentTableActions::make('approve')
                    ->label('Approve Payout')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (MetalWithdrawal $record): bool => $record->isPending())
                    ->form([
                        Forms\Components\TextInput::make('payout_reference')
                            ->label('Bank UTR / Reference (optional)')
                            ->maxLength(100),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Approve metal withdrawal')
                    ->modalDescription('Metal holdings will be deducted and the payout marked as paid to the customer bank account.')
                    ->action(function (MetalWithdrawal $record, array $data): void {
                        app(MetalWithdrawalService::class)->approve(
                            $record,
                            Auth::guard('admin')->id(),
                            $data['payout_reference'] ?? null,
                        );

                        Notification::make()
                            ->title('Withdrawal approved')
                            ->success()
                            ->send();
                    }),
                FilamentTableActions::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (MetalWithdrawal $record): bool => $record->isPending())
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->maxLength(1000),
                    ])
                    ->requiresConfirmation()
                    ->action(function (MetalWithdrawal $record, array $data): void {
                        app(MetalWithdrawalService::class)->reject(
                            $record,
                            (int) Auth::guard('admin')->id(),
                            $data['rejection_reason'],
                        );

                        Notification::make()
                            ->title('Withdrawal rejected')
                            ->warning()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMetalWithdrawals::route('/'),
            'view' => Pages\ViewMetalWithdrawal::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        return NavigationBadgeCounts::format(NavigationBadgeCounts::pendingMetalWithdrawals());
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
