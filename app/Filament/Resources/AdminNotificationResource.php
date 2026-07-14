<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Filament\Resources\AdminNotificationResource\Pages;
use App\Models\AdminNotification;
use App\Services\NotificationInboxService;
use App\Support\FilamentTableActions;
use App\Support\NavigationBadgeCounts;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AdminNotificationResource extends Resource
{
    use InteractsWithAdminPermissions;

    protected static function adminPermissionModule(): string
    {
        return 'admin_notifications';
    }

    protected static ?string $model = AdminNotification::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell';

    protected static ?string $navigationGroup = 'Notification Management';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Inbox';

    protected static ?string $modelLabel = 'Notification';

    protected static ?string $pluralModelLabel = 'Inbox';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')->disabled(),
                Forms\Components\Textarea::make('body')->disabled()->columnSpanFull(),
                Forms\Components\TextInput::make('type')->disabled(),
                Forms\Components\DateTimePicker::make('read_at')->disabled()->native(false),
                Forms\Components\DateTimePicker::make('created_at')->disabled()->native(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                $admin = Filament::auth()->user();

                return $query->where('admin_id', $admin?->getKey());
            })
            ->columns([
                Tables\Columns\IconColumn::make('read_at')
                    ->label('')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-bell-alert')
                    ->trueColor('gray')
                    ->falseColor('warning')
                    ->getStateUsing(fn (AdminNotification $record): bool => $record->read_at !== null),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->weight(fn (AdminNotification $record) => $record->read_at ? null : 'bold')
                    ->wrap(),
                Tables\Columns\TextColumn::make('body')
                    ->limit(60)
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->label('Type')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Received')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('read_at')
                    ->label('Read')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Unread')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('unread')
                    ->label('Unread only')
                    ->placeholder('All')
                    ->trueLabel('Unread')
                    ->falseLabel('Read')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNull('read_at'),
                        false: fn (Builder $query) => $query->whereNotNull('read_at'),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->actions([
                FilamentTableActions::view(),
                FilamentTableActions::make('mark_read')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->tooltip('Mark as read')
                    ->visible(fn (AdminNotification $record) => $record->read_at === null)
                    ->action(function (AdminNotification $record): void {
                        $record->markRead();
                        NavigationBadgeCounts::forgetUnreadAdminNotifications();
                        Notification::make()
                            ->title('Marked as read')
                            ->success()
                            ->send();
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('mark_all_read')
                    ->label('Mark all as read')
                    ->icon('heroicon-o-check-badge')
                    ->requiresConfirmation()
                    ->action(function (NotificationInboxService $inbox): void {
                        $admin = Filament::auth()->user();
                        if ($admin) {
                            $inbox->markAllReadFor($admin);
                            NavigationBadgeCounts::forgetUnreadAdminNotifications();
                        }
                        Notification::make()
                            ->title('All notifications marked as read')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdminNotifications::route('/'),
            'view' => Pages\ViewAdminNotification::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        return NavigationBadgeCounts::format(NavigationBadgeCounts::unreadAdminNotifications());
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
