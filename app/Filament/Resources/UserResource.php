<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers\KycDetailRelationManager;
use App\Models\User;
use App\Support\FilamentDateFilters;
use App\Support\FilamentFormFields;
use App\Support\FilamentTableActions;
use App\Support\KycPayload;
use App\Support\NavigationBadgeCounts;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
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
        return parent::getEloquentQuery()->with(['referredBy', 'kycDetail']);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('User Overview')
                    ->schema([
                        Infolists\Components\TextEntry::make('name'),
                        Infolists\Components\TextEntry::make('phone')->label('Mobile'),
                        Infolists\Components\TextEntry::make('email')->placeholder('—'),
                        Infolists\Components\TextEntry::make('date_of_birth')
                            ->label('Date of Birth')
                            ->date('d M Y')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('kyc_status')
                            ->label('KYC Status')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', ucfirst($state)))
                            ->color(fn (string $state): string => match ($state) {
                                'approved' => 'success',
                                'rejected' => 'danger',
                                'under_review', 'submitted' => 'warning',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('account_status')
                            ->label('Account Status')
                            ->badge()
                            ->getStateUsing(fn (User $record): string => $record->is_blocked ? 'Blocked' : 'Active')
                            ->color(fn (User $record): string => $record->is_blocked ? 'danger' : 'success'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Registered')
                            ->dateTime('d M Y, h:i A'),
                    ])->columns(3),

                Infolists\Components\Section::make('KYC Details')
                    ->description(fn (User $record): ?string => $record->kycDetail && KycPayload::isSurepassPanBankVerified($record->kycDetail)
                        ? 'PAN, Aadhaar, and bank verified via Surepass — no manual admin approval required.'
                        : null)
                    ->schema([
                        Infolists\Components\TextEntry::make('kycDetail.full_name')
                            ->label('Full Name')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('kycDetail.pan_number')
                            ->label('PAN Number')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('kycDetail.pan_verification_status')
                            ->label('PAN Status')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => filled($state)
                                ? str($state)->replace('_', ' ')->title()
                                : '—')
                            ->color(fn (?string $state): string => match ($state) {
                                'verified' => 'success',
                                'rejected' => 'danger',
                                default => 'warning',
                            }),
                        Infolists\Components\TextEntry::make('kycDetail.pan_verified_at')
                            ->label('PAN Verified At')
                            ->dateTime('d M Y, h:i A')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('kycDetail.date_of_birth')
                            ->label('Date of Birth')
                            ->date('d M Y')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('kycDetail.aadhaar_number')
                            ->label('Aadhaar')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('kycDetail.aadhaar_verification_status')
                            ->label('Aadhaar Status')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => filled($state)
                                ? str($state)->replace('_', ' ')->title()
                                : '—')
                            ->color(fn (?string $state): string => $state === 'verified' ? 'success' : 'warning'),
                        Infolists\Components\TextEntry::make('kycDetail.account_holder_name')
                            ->label('Account Holder')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('kycDetail.bank_name')
                            ->label('Bank Name')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('kycDetail.account_number')
                            ->label('Account Number')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('kycDetail.ifsc_code')
                            ->label('IFSC')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('kycDetail.bank_verification_status')
                            ->label('Bank Status')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => filled($state)
                                ? str($state)->replace('_', ' ')->title()
                                : '—')
                            ->color(fn (?string $state): string => in_array($state, ['verified', 'approved'], true) ? 'success' : 'warning'),
                        Infolists\Components\TextEntry::make('kycDetail.bank_submitted_at')
                            ->label('Bank Verified At')
                            ->dateTime('d M Y, h:i A')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('kycDetail.face_verification_status')
                            ->label('Face Status')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => filled($state)
                                ? str($state)->replace('_', ' ')->title()
                                : '—')
                            ->color(fn (?string $state): string => match ($state) {
                                'approved' => 'success',
                                'rejected' => 'danger',
                                default => 'warning',
                            }),
                        Infolists\Components\TextEntry::make('kycDetail.submitted_at')
                            ->label('KYC Submitted At')
                            ->dateTime('d M Y, h:i A')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('kycDetail.reviewed_at')
                            ->label('KYC Reviewed At')
                            ->dateTime('d M Y, h:i A')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('surepass_auto_approved')
                            ->label('Surepass Auto Approval')
                            ->badge()
                            ->getStateUsing(fn (User $record): string => $record->kycDetail && KycPayload::isSurepassPanBankVerified($record->kycDetail)
                                ? 'Auto Approved'
                                : 'Manual Review Required')
                            ->color(fn (User $record): string => $record->kycDetail && KycPayload::isSurepassPanBankVerified($record->kycDetail)
                                ? 'success'
                                : 'warning'),
                    ])
                    ->columns(3)
                    ->visible(fn (User $record): bool => $record->kycDetail !== null),

                Infolists\Components\Section::make('Holdings & Wallet')
                    ->schema([
                        Infolists\Components\TextEntry::make('gold_holdings')
                            ->suffix(' g'),
                        Infolists\Components\TextEntry::make('silver_holdings')
                            ->suffix(' g'),
                        Infolists\Components\TextEntry::make('wallet_balance')
                            ->money('INR'),
                    ])->columns(3),
            ]);
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
                        Forms\Components\DatePicker::make('date_of_birth')
                            ->label('Date of Birth')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->maxDate(now()->subYears(18))
                            ->minDate(now()->subYears(100))
                            ->helperText('User must be at least 18 years old.'),
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
                        FilamentFormFields::relation('nominee_relation', 'Relation', false),
                        FilamentFormFields::mobile('nominee_phone', 'Nominee Mobile', false),
                        Forms\Components\DatePicker::make('nominee_date_of_birth')
                            ->label('Nominee Date of Birth')
                            ->native(false)
                            ->maxDate(now()),
                    ])->columns(2),

                Forms\Components\Section::make('Account Status')
                    ->schema([
                        Forms\Components\Hidden::make('role')
                            ->default('user')
                            ->dehydrated(),
                        Forms\Components\Select::make('kyc_status')
                            ->options([
                                'pending' => 'Pending',
                                'submitted' => 'Submitted',
                                'under_review' => 'Under Review',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                            ])
                            ->required()
                            ->default('pending'),
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
