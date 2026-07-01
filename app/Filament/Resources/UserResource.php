<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers\KycDetailRelationManager;
use App\Models\User;
use App\Support\FilamentDateFilters;
use App\Support\FilamentFormFields;
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

class UserResource extends Resource
{
    use InteractsWithAdminPermissions;

    protected static function adminPermissionModule(): string
    {
        return 'users';
    }

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'User Management';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Manage Users';

    protected static ?string $modelLabel = 'User';

    protected static ?string $pluralModelLabel = 'Users';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('referredBy');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Registration Details')
                    ->schema([
                        FilamentFormFields::name()
                            ->required(),
                        FilamentFormFields::mobile()
                            ->required()
                            ->unique(ignoreRecord: true),
                        FilamentFormFields::mpin()
                            ->required(fn (string $operation) => $operation === 'create')
                            ->visible(fn (string $operation) => $operation === 'create'),
                        FilamentFormFields::mpin('mpin', 'New MPIN', false)
                            ->helperText('Leave blank to keep current MPIN.')
                            ->visible(fn (string $operation) => $operation === 'edit'),
                        Forms\Components\TextInput::make('referral_code_input')
                            ->label('Referral Code (optional)')
                            ->maxLength(12)
                            ->visible(fn (string $operation) => $operation === 'create')
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('referral_code')
                            ->label('User Referral Code')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (string $operation) => $operation === 'edit'),
                        Forms\Components\Placeholder::make('referred_by_display')
                            ->label('Referred By')
                            ->content(fn (?User $record): string => $record?->referredBy
                                ? $record->referredBy->name.' ('.$record->referredBy->phone.')'
                                : '—')
                            ->visible(fn (?User $record) => $record?->referred_by_id !== null),
                    ])->columns(2),

                Forms\Components\Section::make('Nominee Details')
                    ->description('Nominee information for the user profile.')
                    ->schema([
                        FilamentFormFields::fullName('nominee_name', 'Nominee Name', false),
                        Forms\Components\TextInput::make('nominee_relation')
                            ->label('Relation')
                            ->maxLength(50),
                        FilamentFormFields::mobile('nominee_phone', 'Nominee Mobile', false),
                        Forms\Components\DatePicker::make('nominee_date_of_birth')
                            ->label('Nominee Date of Birth')
                            ->native(false)
                            ->maxDate(now()),
                    ])->columns(2),

                Forms\Components\Section::make('Account Status')
                    ->schema([
                        Forms\Components\Select::make('role')
                            ->options([
                                'user' => 'User',
                                'investor' => 'Investor',
                            ])
                            ->required()
                            ->default('user'),
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
                    ->description('Holdings are calculated automatically from completed buy/sell transactions. Wallet balance updates via wallet transactions.')
                    ->schema([
                        Forms\Components\TextInput::make('gold_holdings')
                            ->numeric()
                            ->suffix('grams')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('silver_holdings')
                            ->numeric()
                            ->suffix('grams')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('wallet_balance')
                            ->numeric()
                            ->prefix('₹')
                            ->disabled()
                            ->dehydrated(false),
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

                Tables\Columns\TextColumn::make('referral_code')
                    ->label('Referral Code')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

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
