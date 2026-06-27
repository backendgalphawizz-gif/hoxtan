<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PushNotificationResource\Pages;
use App\Models\PushNotification;
use App\Models\User;
use App\Support\FilamentDateFilters;
use App\Support\FilamentTableActions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PushNotificationResource extends Resource
{
    protected static ?string $model = PushNotification::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationGroup = 'Notification Management';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Push Notifications';

    protected static ?string $modelLabel = 'Push Notification';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Notification Content')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('body')
                            ->required()
                            ->maxLength(1000)
                            ->columnSpanFull(),
                        Forms\Components\Select::make('target')
                            ->options([
                                'all' => 'All Users',
                                'investors' => 'Investors Only',
                                'specific' => 'Specific Users',
                            ])
                            ->required()
                            ->default('all')
                            ->live(),
                        Forms\Components\Select::make('target_user_ids')
                            ->label('Target Users')
                            ->multiple()
                            ->options(fn () => User::query()->pluck('name', 'id'))
                            ->searchable()
                            ->visible(fn (Forms\Get $get) => $get('target') === 'specific')
                            ->required(fn (Forms\Get $get) => $get('target') === 'specific'),
                        Forms\Components\Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'scheduled' => 'Scheduled',
                                'sent' => 'Sent',
                                'failed' => 'Failed',
                            ])
                            ->required()
                            ->default('draft')
                            ->disabled(fn (?PushNotification $record) => $record?->status === 'sent'),
                        Forms\Components\DateTimePicker::make('scheduled_at')
                            ->native(false)
                            ->visible(fn (Forms\Get $get) => $get('status') === 'scheduled')
                            ->required(fn (Forms\Get $get) => $get('status') === 'scheduled')
                            ->minDate(now())
                            ->rules(['nullable', 'date', 'after:now']),
                        Forms\Components\DateTimePicker::make('sent_at')
                            ->native(false)
                            ->disabled()
                            ->visible(fn (?PushNotification $record) => $record?->sent_at !== null),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('body')
                    ->limit(40)
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('target')
                    ->colors([
                        'primary' => 'all',
                        'success' => 'investors',
                        'warning' => 'specific',
                    ]),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray' => 'draft',
                        'warning' => 'scheduled',
                        'success' => 'sent',
                        'danger' => 'failed',
                    ]),
                Tables\Columns\TextColumn::make('scheduled_at')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sent_at')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Created By')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'scheduled' => 'Scheduled',
                        'sent' => 'Sent',
                        'failed' => 'Failed',
                    ]),
                Tables\Filters\SelectFilter::make('target')
                    ->options([
                        'all' => 'All Users',
                        'investors' => 'Investors Only',
                        'specific' => 'Specific Users',
                    ]),
                FilamentDateFilters::tableFilter('scheduled_date', 'scheduled_at', 'Scheduled Date', allowFuture: true),
                FilamentDateFilters::tableFilter('created_date', 'created_at', 'Created Date'),
            ])
            ->actions([
                FilamentTableActions::view(),
                FilamentTableActions::edit()
                    ->visible(fn (PushNotification $record) => $record->status !== 'sent'),
                FilamentTableActions::make('send')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->tooltip('Send Now')
                    ->visible(fn (PushNotification $record) => in_array($record->status, ['draft', 'scheduled']))
                    ->requiresConfirmation()
                    ->modalHeading('Send Push Notification')
                    ->modalDescription('This will mark the notification as sent and dispatch it to the target audience.')
                    ->action(function (PushNotification $record) {
                        $record->update([
                            'status' => 'sent',
                            'sent_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Push notification sent')
                            ->body('Notification "'.$record->title.'" has been dispatched.')
                            ->success()
                            ->send();
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
            'index' => Pages\ListPushNotifications::route('/'),
            'create' => Pages\CreatePushNotification::route('/create'),
            'view' => Pages\ViewPushNotification::route('/{record}'),
            'edit' => Pages\EditPushNotification::route('/{record}/edit'),
        ];
    }
}
