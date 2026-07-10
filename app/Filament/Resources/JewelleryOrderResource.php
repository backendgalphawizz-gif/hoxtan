<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Filament\Resources\JewelleryOrderResource\Pages;
use App\Models\Driver;
use App\Models\JewelleryOrder;
use App\Support\FilamentDateFilters;
use App\Support\FilamentTableActions;
use App\Support\NavigationBadgeCounts;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class JewelleryOrderResource extends Resource
{
    use InteractsWithAdminPermissions;

    protected static ?string $model = JewelleryOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Jewellery Management';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Buy Now Orders';

    protected static ?string $modelLabel = 'Jewellery Order';

    protected static ?string $pluralModelLabel = 'Buy Now Orders';

    protected static function adminPermissionModule(): string
    {
        return 'jewellery_orders';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user', 'payment', 'items.product', 'address', 'driver']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Order Details')
                    ->schema([
                        Forms\Components\TextInput::make('order_number')
                            ->label('Order Number')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'name')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'processing' => 'Processing',
                                'completed' => 'Completed',
                                'failed' => 'Failed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->required(),
                        Forms\Components\DatePicker::make('expected_delivery_date')
                            ->label('Expected Delivery'),
                    ])->columns(2),
                Forms\Components\Section::make('Pricing')
                    ->schema([
                        Forms\Components\TextInput::make('metal_value')
                            ->label('Metal Value')
                            ->prefix('₹')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('making_charge_amount')
                            ->label('Making Charges')
                            ->prefix('₹')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('subtotal')
                            ->label('Subtotal')
                            ->prefix('₹')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('gst_percent')
                            ->label('GST %')
                            ->suffix('%')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('gst_amount')
                            ->label('GST Amount')
                            ->prefix('₹')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('discount_amount')
                            ->label('Discount')
                            ->prefix('₹')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('total_amount')
                            ->label('Total Amount')
                            ->prefix('₹')
                            ->disabled()
                            ->dehydrated(false),
                    ])->columns(3),
                Forms\Components\Section::make('Delivery Address')
                    ->schema([
                        Forms\Components\TextInput::make('shipping_name')
                            ->label('Recipient')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('shipping_phone')
                            ->label('Phone')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('shipping_address_type')
                            ->label('Address Type')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Textarea::make('shipping_address')
                            ->label('Address')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ])->columns(3),
                Forms\Components\Section::make('Delivery Tracking')
                    ->schema([
                        static::driverAssignmentSelect()
                            ->helperText('Only active drivers are listed. Online/offline status is shown for reference.'),
                        Forms\Components\DateTimePicker::make('driver_assigned_at')
                            ->label('Driver Assigned At')
                            ->disabled()
                            ->dehydrated(false)
                            ->native(false)
                            ->visible(fn (Forms\Get $get): bool => filled($get('driver_id'))),
                        Forms\Components\TextInput::make('tracking_number')
                            ->label('Tracking Number')
                            ->maxLength(100)
                            ->disabled(fn (string $operation): bool => $operation === 'view'),
                        Forms\Components\TextInput::make('courier_name')
                            ->label('Courier Name')
                            ->maxLength(100)
                            ->disabled(fn (string $operation): bool => $operation === 'view'),
                        Forms\Components\DateTimePicker::make('dispatched_at')
                            ->label('Dispatched At')
                            ->native(false)
                            ->disabled(fn (string $operation): bool => $operation === 'view'),
                        Forms\Components\DateTimePicker::make('delivered_at')
                            ->label('Delivered At')
                            ->native(false)
                            ->disabled(fn (string $operation): bool => $operation === 'view'),
                    ])->columns(2),
                Forms\Components\Section::make('Payment')
                    ->schema([
                        Forms\Components\Placeholder::make('payment_reference')
                            ->label('Payment Reference')
                            ->content(fn (?JewelleryOrder $record): string => $record?->payment?->reference_id ?? '—'),
                        Forms\Components\Placeholder::make('payment_status')
                            ->label('Payment Status')
                            ->content(fn (?JewelleryOrder $record): string => strtoupper((string) ($record?->payment?->status ?? '—'))),
                        Forms\Components\Placeholder::make('payment_amount')
                            ->label('Payment Amount')
                            ->content(fn (?JewelleryOrder $record): string => $record?->payment
                                ? '₹'.number_format((float) $record->payment->amount, 2)
                                : '—'),
                    ])->columns(3)
                    ->visible(fn (?JewelleryOrder $record): bool => $record?->payment !== null),
                Forms\Components\Section::make('Order Items')
                    ->schema([
                        Forms\Components\Placeholder::make('order_items')
                            ->label('')
                            ->content(function (?JewelleryOrder $record): string {
                                if (! $record) {
                                    return '—';
                                }

                                return $record->items
                                    ->map(function ($item): string {
                                        $name = $item->product?->name ?? 'Product #'.$item->jewellery_product_id;
                                        $qty = $item->quantity;
                                        $lineTotal = number_format((float) $item->line_total, 2);

                                        return "{$name} × {$qty} — ₹{$lineTotal}";
                                    })
                                    ->implode("\n") ?: '—';
                            }),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable()
                    ->copyable()
                    ->formatStateUsing(fn (string $state): string => '#'.$state),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.phone')
                    ->label('Phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('product_summary')
                    ->label('Product')
                    ->getStateUsing(fn (JewelleryOrder $record): string => $record->items
                        ->map(fn ($item) => ($item->product?->name ?? 'Product').' × '.$item->quantity)
                        ->implode(', ') ?: '—')
                    ->wrap(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'processing',
                        'success' => 'completed',
                        'danger' => fn ($state) => in_array($state, ['failed', 'cancelled'], true),
                        'gray' => 'cart',
                    ]),
                Tables\Columns\BadgeColumn::make('payment.status')
                    ->label('Payment')
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'processing',
                        'success' => 'completed',
                        'danger' => 'failed',
                        'gray' => 'refunded',
                    ]),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->inr()
                    ->sortable(),
                Tables\Columns\TextColumn::make('driver.name')
                    ->label('Driver')
                    ->description(fn (JewelleryOrder $record): ?string => $record->driver
                        ? '+91 '.$record->driver->phone
                        : null)
                    ->placeholder('Unassigned')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('expected_delivery_date')
                    ->label('Delivery By')
                    ->date('d M Y')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('tracking_number')
                    ->label('Tracking #')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ordered At')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'cancelled' => 'Cancelled',
                    ]),
                Tables\Filters\SelectFilter::make('driver_id')
                    ->label('Driver')
                    ->relationship('driver', 'name')
                    ->searchable()
                    ->preload(),
                FilamentDateFilters::tableFilter('ordered_date', 'created_at', 'Order Date'),
            ])
            ->actions([
                FilamentTableActions::view(),
                FilamentTableActions::edit(),
                FilamentTableActions::make('assign_driver')
                    ->icon('heroicon-o-user-plus')
                    ->color('info')
                    ->tooltip('Assign Driver')
                    ->visible(fn (JewelleryOrder $record): bool => in_array($record->status, ['pending', 'processing'], true))
                    ->form(fn (JewelleryOrder $record): array => [
                        static::driverAssignmentSelect($record->driver_id)
                            ->label('Driver')
                            ->required(),
                    ])
                    ->action(function (JewelleryOrder $record, array $data): void {
                        $record->update([
                            'driver_id' => $data['driver_id'],
                        ]);

                        Notification::make()
                            ->title('Driver assigned to order')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No buy now orders yet')
            ->emptyStateDescription('Orders from the mobile Buy Now API will appear here.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJewelleryOrders::route('/'),
            'view' => Pages\ViewJewelleryOrder::route('/{record}'),
            'edit' => Pages\EditJewelleryOrder::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        return NavigationBadgeCounts::format(NavigationBadgeCounts::pendingJewelleryOrders());
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function driverAssignmentSelect(?int $includeDriverId = null): Forms\Components\Select
    {
        return Forms\Components\Select::make('driver_id')
            ->label('Assigned Driver')
            ->options(function (?JewelleryOrder $record) use ($includeDriverId): array {
                return Driver::assignmentOptions($includeDriverId ?? $record?->driver_id);
            })
            ->placeholder('Select a driver')
            ->nullable()
            ->live()
            ->afterStateUpdated(function (?int $state, JewelleryOrder $record, Forms\Set $set, $livewire): void {
                if (! $livewire instanceof Pages\ViewJewelleryOrder) {
                    return;
                }

                $record->update(['driver_id' => $state]);
                $record->refresh();

                $set('driver_assigned_at', $record->driver_assigned_at);

                Notification::make()
                    ->title(filled($state) ? 'Driver assigned to order' : 'Driver unassigned from order')
                    ->success()
                    ->send();
            });
    }
}
