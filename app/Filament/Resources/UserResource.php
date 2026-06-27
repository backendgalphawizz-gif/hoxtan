<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers\KycDetailRelationManager;
use App\Models\User;
use App\Support\FilamentDateFilters;
use App\Support\FilamentTableActions;
use App\Support\NavigationBadgeCounts;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'User Management';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Manage Users';

    protected static ?string $modelLabel = 'User';

    protected static ?string $pluralModelLabel = 'Users';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->unique(ignoreRecord: true)
                            ->regex('/^[6-9]\d{9}$/')
                            ->validationMessages([
                                'regex' => 'Enter a valid 10-digit Indian mobile number.',
                            ])
                            ->maxLength(20),
                        Forms\Components\Select::make('role')
                            ->options([
                                'user' => 'User',
                                'investor' => 'Investor',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $operation) => $operation === 'create')
                            ->minLength(8)
                            ->maxLength(255),
                    ])->columns(2),

                Forms\Components\Section::make('Account Status')
                    ->schema([
                        Forms\Components\Select::make('kyc_status')
                            ->options([
                                'pending' => 'Pending',
                                'submitted' => 'Submitted',
                                'under_review' => 'Under Review',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                            ])
                            ->required(),
                        Forms\Components\Toggle::make('is_verified')
                            ->label('Account Verified'),
                        Forms\Components\Toggle::make('is_blocked')
                            ->label('Account Blocked')
                            ->live(),
                        Forms\Components\Textarea::make('block_reason')
                            ->label('Block Reason')
                            ->visible(fn (Forms\Get $get) => $get('is_blocked'))
                            ->required(fn (Forms\Get $get) => $get('is_blocked'))
                            ->maxLength(500),
                    ])->columns(2),

                Forms\Components\Section::make('Holdings & Wallet')
                    ->schema([
                        Forms\Components\TextInput::make('gold_holdings')
                            ->numeric()
                            ->suffix('grams')
                            ->minValue(0)
                            ->step(0.0001),
                        Forms\Components\TextInput::make('silver_holdings')
                            ->numeric()
                            ->suffix('grams')
                            ->minValue(0)
                            ->step(0.0001),
                        Forms\Components\TextInput::make('wallet_balance')
                            ->numeric()
                            ->prefix('₹')
                            ->minValue(0)
                            ->step(0.01),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('index')
                    ->label('N.')
                    ->rowIndex(),

                Tables\Columns\TextColumn::make('id')
                    ->label('User ID')
                    ->formatStateUsing(fn (int $state): string => 'USR'.str_pad((string) $state, 5, '0', STR_PAD_LEFT))
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->html()
                    ->formatStateUsing(function (User $record): string {
                        $initials = collect(explode(' ', $record->name))
                            ->filter()
                            ->map(fn (string $part): string => strtoupper(substr($part, 0, 1)))
                            ->take(2)
                            ->join('');

                        return '<div class="gs-user-cell">'
                            .'<span class="gs-user-avatar">'.e($initials).'</span>'
                            .'<span class="gs-user-name">'.e($record->name).'</span>'
                            .'</div>';
                    })
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Mobile No')
                    ->searchable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('investments_count')
                    ->label('Investments')
                    ->counts('investments')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registered')
                    ->date('M d, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('account_status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn (User $record): string => $record->is_blocked ? 'Blocked' : 'Active')
                    ->color(fn (User $record): string => $record->is_blocked ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('kyc_status')
                    ->label('KYC')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', ucfirst($state)))
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'under_review' => 'warning',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('account_status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'blocked' => 'Blocked',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'active' => $query->where('is_blocked', false),
                            'blocked' => $query->where('is_blocked', true),
                            default => $query,
                        };
                    }),

                Tables\Filters\SelectFilter::make('kyc_status')
                    ->label('KYC Status')
                    ->options([
                        'pending' => 'Pending',
                        'submitted' => 'Submitted',
                        'under_review' => 'Under Review',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),

                Filter::make('registered_between')
                    ->label('Registration Date')
                    ->form(FilamentDateFilters::rangeFields(
                        fromLabel: 'Registered From',
                        toLabel: 'Registered To',
                    ))
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => FilamentDateFilters::applyRange(
                        $query,
                        $data,
                        'created_at',
                    )),

                Tables\Filters\SelectFilter::make('role')
                    ->options(['user' => 'User', 'investor' => 'Investor']),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->actions([
                FilamentTableActions::view(),
                FilamentTableActions::edit(),
                FilamentTableActions::make('block')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->tooltip('Block')
                    ->visible(fn (User $record) => ! $record->is_blocked)
                    ->form([
                        Forms\Components\Textarea::make('block_reason')
                            ->required()
                            ->maxLength(500),
                    ])
                    ->action(function (User $record, array $data) {
                        $record->update([
                            'is_blocked' => true,
                            'blocked_at' => now(),
                            'block_reason' => $data['block_reason'],
                        ]);
                        Notification::make()->title('Account blocked')->danger()->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped(false)
            ->paginated([10, 25, 50]);
    }

    public static function getRelations(): array
    {
        return [
            KycDetailRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return NavigationBadgeCounts::format(NavigationBadgeCounts::usersAwaitingKycReview());
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }
}
