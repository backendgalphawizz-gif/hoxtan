<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RedemptionResource\Pages;
use App\Models\Redemption;
use App\Support\FilamentDateFilters;
use App\Support\FilamentTableActions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class RedemptionResource extends Resource
{
    protected static ?string $model = Redemption::class;

    protected static ?string $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationGroup = 'Redemption Management';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Redemption Requests';

    protected static ?string $modelLabel = 'Redemption';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Request Details')
                    ->schema([
                        Forms\Components\TextInput::make('reference_id')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (?Redemption $record) => $record !== null),
                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn (?Redemption $record) => $record !== null),
                        Forms\Components\Select::make('metal_type')
                            ->options(['gold' => 'Gold', 'silver' => 'Silver'])
                            ->required(),
                        Forms\Components\TextInput::make('quantity_grams')
                            ->label('Quantity (grams)')
                            ->required()
                            ->numeric()
                            ->minValue(0.0001)
                            ->step(0.0001)
                            ->suffix('g'),
                        Forms\Components\TextInput::make('amount')
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->prefix('₹'),
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'approved' => 'Approved',
                                'processing' => 'Processing',
                                'dispatched' => 'Dispatched',
                                'delivered' => 'Delivered',
                                'rejected' => 'Rejected',
                                'cancelled' => 'Cancelled',
                            ])
                            ->required()
                            ->default('pending'),
                    ])->columns(2),

                Forms\Components\Section::make('Delivery Information')
                    ->schema([
                        Forms\Components\Textarea::make('delivery_address')
                            ->required()
                            ->maxLength(1000)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('courier_name')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('tracking_number')
                            ->maxLength(255),
                        Forms\Components\DateTimePicker::make('dispatched_at')
                            ->native(false)
                            ->maxDate(now())
                            ->live()
                            ->rules(['nullable', 'date']),
                        Forms\Components\DateTimePicker::make('delivered_at')
                            ->native(false)
                            ->minDate(fn (Forms\Get $get) => $get('dispatched_at') ? \Carbon\Carbon::parse($get('dispatched_at')) : null)
                            ->maxDate(now())
                            ->after('dispatched_at')
                            ->rules(['nullable', 'date', 'after:dispatched_at']),
                    ])->columns(2),

                Forms\Components\Section::make('Admin Notes')
                    ->schema([
                        Forms\Components\Textarea::make('admin_notes')
                            ->maxLength(1000)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('rejection_reason')
                            ->maxLength(1000)
                            ->visible(fn (Forms\Get $get) => $get('status') === 'rejected')
                            ->required(fn (Forms\Get $get) => $get('status') === 'rejected')
                            ->columnSpanFull(),
                    ]),
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
                Tables\Columns\BadgeColumn::make('metal_type')
                    ->colors(['warning' => 'gold', 'gray' => 'silver']),
                Tables\Columns\TextColumn::make('quantity_grams')
                    ->label('Qty (g)')
                    ->grams(4),
                Tables\Columns\TextColumn::make('amount')
                    ->inr()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'approved',
                        'primary' => 'processing',
                        'success' => fn ($state) => in_array($state, ['dispatched', 'delivered']),
                        'danger' => fn ($state) => in_array($state, ['rejected', 'cancelled']),
                    ]),
                Tables\Columns\TextColumn::make('tracking_number')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'processing' => 'Processing',
                        'dispatched' => 'Dispatched',
                        'delivered' => 'Delivered',
                        'rejected' => 'Rejected',
                        'cancelled' => 'Cancelled',
                    ]),
                Tables\Filters\SelectFilter::make('metal_type')
                    ->options(['gold' => 'Gold', 'silver' => 'Silver']),
                Tables\Filters\SelectFilter::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->label('User'),
                FilamentDateFilters::tableFilter('request_date', 'created_at', 'Request Date'),
            ])
            ->actions([
                FilamentTableActions::view(),
                FilamentTableActions::edit(),
                FilamentTableActions::make('approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->tooltip('Approve')
                    ->visible(fn (Redemption $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->action(function (Redemption $record) {
                        $record->update([
                            'status' => 'approved',
                            'processed_by' => Auth::guard('admin')->id(),
                        ]);
                        Notification::make()->title('Redemption approved')->success()->send();
                    }),
                FilamentTableActions::make('reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->tooltip('Reject')
                    ->visible(fn (Redemption $record) => in_array($record->status, ['pending', 'approved', 'processing']))
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->required()
                            ->maxLength(1000),
                    ])
                    ->action(function (Redemption $record, array $data) {
                        $record->update([
                            'status' => 'rejected',
                            'rejection_reason' => $data['rejection_reason'],
                            'processed_by' => Auth::guard('admin')->id(),
                        ]);
                        Notification::make()->title('Redemption rejected')->danger()->send();
                    }),
                FilamentTableActions::make('dispatch')
                    ->icon('heroicon-o-truck')
                    ->color('info')
                    ->tooltip('Dispatch')
                    ->visible(fn (Redemption $record) => in_array($record->status, ['approved', 'processing']))
                    ->form([
                        Forms\Components\TextInput::make('courier_name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('tracking_number')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->action(function (Redemption $record, array $data) {
                        $record->update([
                            'status' => 'dispatched',
                            'courier_name' => $data['courier_name'],
                            'tracking_number' => $data['tracking_number'],
                            'dispatched_at' => now(),
                            'processed_by' => Auth::guard('admin')->id(),
                        ]);
                        Notification::make()->title('Redemption dispatched')->success()->send();
                    }),
                FilamentTableActions::make('deliver')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->tooltip('Deliver')
                    ->visible(fn (Redemption $record) => $record->status === 'dispatched')
                    ->requiresConfirmation()
                    ->action(function (Redemption $record) {
                        $record->update([
                            'status' => 'delivered',
                            'delivered_at' => now(),
                            'processed_by' => Auth::guard('admin')->id(),
                        ]);
                        Notification::make()->title('Redemption marked as delivered')->success()->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRedemptions::route('/'),
            'create' => Pages\CreateRedemption::route('/create'),
            'view' => Pages\ViewRedemption::route('/{record}'),
            'edit' => Pages\EditRedemption::route('/{record}/edit'),
        ];
    }
}
