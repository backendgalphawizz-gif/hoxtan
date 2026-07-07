<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Filament\Resources\SigPlanResource\Pages;
use App\Filament\Resources\SigPlanResource\RelationManagers\InstallmentsRelationManager;
use App\Models\SigPlan;
use App\Models\User;
use App\Services\SigPlanService;
use App\Support\FilamentDateFilters;
use App\Support\FilamentFormat;
use App\Support\FilamentTableActions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class SigPlanResource extends Resource
{
    use InteractsWithAdminPermissions;

    protected static function adminPermissionModule(): string
    {
        return 'sig_plans';
    }

    protected static ?string $model = SigPlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?string $navigationGroup = 'Investment Management';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'SIG Management';

    protected static ?string $modelLabel = 'SIG Plan';

    protected static ?string $pluralModelLabel = 'SIG Plans';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('SIG Setup')
                    ->description('Configure systematic gold/silver investment — daily, weekly or monthly auto-debit.')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Client')
                            ->relationship('user', 'name')
                            ->getOptionLabelFromRecordUsing(fn (User $record) => "{$record->name} ({$record->phone})")
                            ->searchable(['name', 'phone', 'email'])
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, ?int $state): void {
                                if (! $state) {
                                    return;
                                }

                                $user = User::query()->with('kycDetail')->find($state);

                                if (! $user?->kycDetail) {
                                    return;
                                }

                                $set('linked_bank_name', $user->kycDetail->bank_name);
                                $set('linked_bank_last4', filled($user->kycDetail->account_number)
                                    ? substr($user->kycDetail->account_number, -4)
                                    : null);
                            }),
                        Forms\Components\Select::make('metal_type')
                            ->options(['gold' => 'Gold 24K', 'silver' => 'Silver'])
                            ->default('gold')
                            ->required(),
                        Forms\Components\Select::make('frequency')
                            ->options([
                                'daily' => 'Daily',
                                'weekly' => 'Weekly',
                                'monthly' => 'Monthly',
                            ])
                            ->default('weekly')
                            ->required()
                            ->live(),
                        Forms\Components\TextInput::make('amount')
                            ->label('Amount per installment')
                            ->required()
                            ->numeric()
                            ->minValue(100)
                            ->step(1)
                            ->prefix('₹'),
                        Forms\Components\TextInput::make('total_installments')
                            ->label('Total installments')
                            ->numeric()
                            ->minValue(1)
                            ->default(fn (Forms\Get $get) => match ($get('frequency')) {
                                'daily' => 365,
                                'weekly' => 52,
                                'monthly' => 12,
                                default => 52,
                            }),
                        Forms\Components\TextInput::make('linked_bank_name')
                            ->label('Linked bank')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('linked_bank_last4')
                            ->label('Bank last 4 digits')
                            ->maxLength(4),
                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'paused' => 'Paused',
                                'stopped' => 'Stopped',
                            ])
                            ->default('active')
                            ->visibleOn('edit')
                            ->required(),
                        Forms\Components\Textarea::make('admin_notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('plan_number')
                    ->label('Plan ID')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.phone')
                    ->label('Mobile')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('title_label')
                    ->label('SIG Plan')
                    ->badge()
                    ->color(fn (SigPlan $record) => $record->metal_type === 'gold' ? 'warning' : 'gray'),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Installment')
                    ->inr()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'success' => 'active',
                        'warning' => 'paused',
                        'danger' => 'stopped',
                    ]),
                Tables\Columns\TextColumn::make('next_debit_at')
                    ->label('Next Auto Debit')
                    ->dateTime('d M Y')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_invested')
                    ->inr()
                    ->sortable(),
                Tables\Columns\TextColumn::make('metal_accumulated_grams')
                    ->label('Metal (g)')
                    ->grams(4),
                Tables\Columns\TextColumn::make('progress_label')
                    ->label('Completed')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('linked_bank_label')
                    ->label('Bank')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('activated_at')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'paused' => 'Paused',
                        'stopped' => 'Stopped',
                    ]),
                Tables\Filters\SelectFilter::make('frequency')
                    ->options([
                        'daily' => 'Daily',
                        'weekly' => 'Weekly',
                        'monthly' => 'Monthly',
                    ]),
                Tables\Filters\SelectFilter::make('metal_type')
                    ->options(['gold' => 'Gold', 'silver' => 'Silver']),
                Tables\Filters\SelectFilter::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Client'),
                FilamentDateFilters::tableFilter('next_debit', 'next_debit_at', 'Next Debit Date', allowFuture: true),
            ])
            ->actions([
                FilamentTableActions::view(),
                static::pauseAction(),
                static::resumeAction(),
                static::stopAction(),
                FilamentTableActions::edit(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('SIG Overview')
                    ->schema([
                        Infolists\Components\TextEntry::make('plan_number')->label('Plan ID'),
                        Infolists\Components\TextEntry::make('user.name')->label('Client'),
                        Infolists\Components\TextEntry::make('user.phone')->label('Mobile'),
                        Infolists\Components\TextEntry::make('title_label')->label('Plan'),
                        Infolists\Components\TextEntry::make('status')->badge()
                            ->color(fn (string $state) => match ($state) {
                                'active' => 'success',
                                'paused' => 'warning',
                                default => 'danger',
                            }),
                        Infolists\Components\TextEntry::make('amount')
                            ->label('Installment')
                            ->formatStateUsing(fn ($state) => FilamentFormat::inr($state)),
                        Infolists\Components\TextEntry::make('next_debit_at')->label('Next Auto Debit')->dateTime('d M Y, h:i A')->placeholder('—'),
                        Infolists\Components\TextEntry::make('linked_bank_label')->label('Linked Bank')->placeholder('—'),
                    ])->columns(3),
                Infolists\Components\Section::make('Progress')
                    ->schema([
                        Infolists\Components\TextEntry::make('total_invested')
                            ->formatStateUsing(fn ($state) => FilamentFormat::inr($state)),
                        Infolists\Components\TextEntry::make('metal_accumulated_grams')
                            ->label('Metal accumulated')
                            ->formatStateUsing(fn ($state) => FilamentFormat::grams($state)),
                        Infolists\Components\TextEntry::make('progress_label')->label('Installments completed'),
                        Infolists\Components\TextEntry::make('activated_at')->dateTime('d M Y, h:i A'),
                        Infolists\Components\TextEntry::make('paused_at')->dateTime('d M Y, h:i A')->placeholder('—'),
                        Infolists\Components\TextEntry::make('stopped_at')->dateTime('d M Y, h:i A')->placeholder('—'),
                    ])->columns(3),
                Infolists\Components\Section::make('Notes')
                    ->schema([
                        Infolists\Components\TextEntry::make('admin_notes')->placeholder('—')->columnSpanFull(),
                    ])
                    ->visible(fn (SigPlan $record) => filled($record->admin_notes)),
            ]);
    }

    public static function pauseAction(): Action
    {
        return FilamentTableActions::make('pause')
            ->icon('heroicon-o-pause')
            ->color('warning')
            ->tooltip('Pause SIG')
            ->visible(fn (SigPlan $record): bool => $record->status === 'active' && static::canEdit($record))
            ->requiresConfirmation()
            ->modalHeading('Pause SIG?')
            ->modalDescription('Automatic deductions will stop until the SIG is resumed.')
            ->modalSubmitActionLabel('Pause SIG')
            ->action(function (SigPlan $record, SigPlanService $service): void {
                $service->pause($record);
                Notification::make()->title('SIG paused')->warning()->send();
            });
    }

    public static function resumeAction(): Action
    {
        return FilamentTableActions::make('resume')
            ->icon('heroicon-o-play')
            ->color('success')
            ->tooltip('Resume SIG')
            ->visible(fn (SigPlan $record): bool => $record->status === 'paused' && static::canEdit($record))
            ->requiresConfirmation()
            ->modalHeading('Resume SIG?')
            ->modalDescription('The next auto-debit will be scheduled based on the plan frequency.')
            ->modalSubmitActionLabel('Resume SIG')
            ->action(function (SigPlan $record, SigPlanService $service): void {
                $service->resume($record);
                Notification::make()->title('SIG resumed')->success()->send();
            });
    }

    public static function stopAction(): Action
    {
        return FilamentTableActions::make('stop')
            ->icon('heroicon-o-stop-circle')
            ->color('danger')
            ->tooltip('Stop SIG')
            ->visible(fn (SigPlan $record): bool => $record->status !== 'stopped' && static::canEdit($record))
            ->requiresConfirmation()
            ->modalHeading('Stop SIG permanently?')
            ->modalDescription('This cannot be undone. The client will need to activate a new SIG.')
            ->modalSubmitActionLabel('Stop SIG')
            ->action(function (SigPlan $record, SigPlanService $service): void {
                $service->stop($record);
                Notification::make()->title('SIG stopped')->danger()->send();
            });
    }

    public static function getRelations(): array
    {
        return [
            InstallmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSigPlans::route('/'),
            'create' => Pages\CreateSigPlan::route('/create'),
            'view' => Pages\ViewSigPlan::route('/{record}'),
            'edit' => Pages\EditSigPlan::route('/{record}/edit'),
        ];
    }
}
