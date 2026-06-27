<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WalletTransactionResource\Pages;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\FilamentDateFilters;
use App\Support\FilamentTableActions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class WalletTransactionResource extends Resource
{
    protected static ?string $model = WalletTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Wallet Management';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Wallet Transactions';

    protected static ?string $modelLabel = 'Wallet Transaction';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Transaction Details')
                    ->schema([
                        Forms\Components\TextInput::make('reference_id')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (?WalletTransaction $record) => $record !== null),
                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->disabled(fn (?WalletTransaction $record) => $record !== null)
                            ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                                if ($state) {
                                    $user = User::find($state);
                                    $set('current_balance_preview', $user?->wallet_balance ?? 0);
                                }
                            }),
                        Forms\Components\Select::make('type')
                            ->options([
                                'credit' => 'Credit',
                                'debit' => 'Debit',
                            ])
                            ->required()
                            ->live()
                            ->disabled(fn (?WalletTransaction $record) => $record !== null),
                        Forms\Components\TextInput::make('amount')
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->prefix('₹')
                            ->live(onBlur: true)
                            ->disabled(fn (?WalletTransaction $record) => $record !== null),
                        Forms\Components\TextInput::make('current_balance_preview')
                            ->label('Current Wallet Balance')
                            ->prefix('₹')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (?WalletTransaction $record) => $record === null),
                        Forms\Components\TextInput::make('balance_after')
                            ->label('Balance After')
                            ->prefix('₹')
                            ->disabled()
                            ->dehydrated()
                            ->visible(fn (?WalletTransaction $record) => $record !== null),
                        Forms\Components\Select::make('source')
                            ->options([
                                'admin' => 'Admin',
                                'investment' => 'Investment',
                                'redemption' => 'Redemption',
                                'refund' => 'Refund',
                                'other' => 'Other',
                            ])
                            ->required()
                            ->default('admin'),
                        Forms\Components\Textarea::make('description')
                            ->required()
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference_id')
                    ->searchable()
                    ->copyable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->colors(['success' => 'credit', 'danger' => 'debit']),
                Tables\Columns\TextColumn::make('amount')
                    ->inr()
                    ->sortable(),
                Tables\Columns\TextColumn::make('balance_after')
                    ->inr()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('source')
                    ->colors([
                        'primary' => 'admin',
                        'success' => 'investment',
                        'warning' => 'redemption',
                        'info' => 'refund',
                        'gray' => 'other',
                    ]),
                Tables\Columns\TextColumn::make('description')
                    ->limit(40)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Created By')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(['credit' => 'Credit', 'debit' => 'Debit']),
                Tables\Filters\SelectFilter::make('source')
                    ->options([
                        'admin' => 'Admin',
                        'investment' => 'Investment',
                        'redemption' => 'Redemption',
                        'refund' => 'Refund',
                        'other' => 'Other',
                    ]),
                Tables\Filters\SelectFilter::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->label('User'),
                FilamentDateFilters::tableFilter('transaction_date', 'created_at', 'Transaction Date'),
            ])
            ->actions([
                FilamentTableActions::view(),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function calculateBalanceAfter(int $userId, string $type, float $amount): float
    {
        $user = User::findOrFail($userId);
        $currentBalance = (float) $user->wallet_balance;

        if ($type === 'credit') {
            return round($currentBalance + $amount, 2);
        }

        if ($amount > $currentBalance) {
            throw ValidationException::withMessages([
                'amount' => 'Insufficient wallet balance. Available: ₹'.number_format($currentBalance, 2),
            ]);
        }

        return round($currentBalance - $amount, 2);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWalletTransactions::route('/'),
            'create' => Pages\CreateWalletTransaction::route('/create'),
            'view' => Pages\ViewWalletTransaction::route('/{record}'),
        ];
    }
}
