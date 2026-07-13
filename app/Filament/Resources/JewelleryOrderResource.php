<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Filament\Resources\JewelleryOrderResource\Pages;
use App\Models\Driver;
use App\Models\JewelleryOrder;
use App\Models\JewelleryOrderListing;
use App\Models\OldGoldBooking;
use App\Support\FilamentDateFilters;
use App\Support\FilamentTableActions;
use App\Support\NavigationBadgeCounts;
use App\Support\SellJewelleryPayload;
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

    protected static ?int $navigationSort = 6;

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
            ->with(['user', 'payment', 'items.product', 'address', 'driver', 'emiPlan']);
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
                Forms\Components\Section::make('EMI')
                    ->schema([
                        Forms\Components\Select::make('payment_mode')
                            ->label('Payment Mode')
                            ->options([
                                'full' => 'Pay in Full',
                                'emi' => 'EMI',
                            ])
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('emi_tenure')
                            ->label('EMI Tenure (months)')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (?JewelleryOrder $record): bool => $record?->payment_mode === 'emi'),
                        Forms\Components\TextInput::make('total_emi_cost')
                            ->label('Total EMI Cost')
                            ->prefix('₹')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (?JewelleryOrder $record): bool => $record?->payment_mode === 'emi'),
                        Forms\Components\TextInput::make('monthly_emi_amount')
                            ->label('Monthly EMI')
                            ->prefix('₹')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (?JewelleryOrder $record): bool => $record?->payment_mode === 'emi'),
                        Forms\Components\Placeholder::make('emi_plan_label')
                            ->label('EMI Plan')
                            ->content(fn (?JewelleryOrder $record): string => $record?->emiPlan?->displayLabel() ?? '—')
                            ->visible(fn (?JewelleryOrder $record): bool => $record?->payment_mode === 'emi'),
                    ])->columns(2)
                    ->visible(fn (?JewelleryOrder $record): bool => $record?->payment_mode === 'emi'),
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
                        Forms\Components\TextInput::make('delivery_otp')
                            ->label('Delivery OTP')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('—'),
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
                Tables\Columns\TextColumn::make('listing_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'sell' ? 'Sell' : 'Buy')
                    ->color(fn (string $state): string => $state === 'sell' ? 'info' : 'success'),
                Tables\Columns\TextColumn::make('reference_number')
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
                    ->getStateUsing(fn (JewelleryOrderListing $record): string => $record->productSummary())
                    ->wrap(),
                Tables\Columns\BadgeColumn::make('status')
                    ->formatStateUsing(function (?string $state, JewelleryOrderListing $record): string {
                        return $record->isSell()
                            ? SellJewelleryPayload::statusLabel($state)
                            : ucfirst((string) $state);
                    })
                    ->colors([
                        'warning' => 'pending',
                        'info' => fn ($state) => in_array($state, ['processing', 'accepted', 'pickup_scheduling'], true),
                        'success' => fn ($state) => in_array($state, ['completed', 'picked_up'], true),
                        'danger' => fn ($state) => in_array($state, ['failed', 'cancelled'], true),
                        'gray' => 'cart',
                    ]),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Total')
                    ->inr()
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_mode')
                    ->label('Payment')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): ?string => match ($state) {
                        'emi' => 'EMI',
                        'full' => 'Full',
                        default => null,
                    })
                    ->color(fn (?string $state): string => $state === 'emi' ? 'info' : 'gray')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('driver.name')
                    ->label('Driver')
                    ->description(fn (JewelleryOrderListing $record): ?string => $record->driver
                        ? '+91 '.$record->driver->phone
                        : null)
                    ->placeholder('Unassigned')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ordered At')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('listing_type')
                    ->label('Type')
                    ->options([
                        'buy' => 'Buy',
                        'sell' => 'Sell',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'accepted' => 'Accepted',
                        'pickup_scheduling' => 'Pickup Scheduling',
                        'picked_up' => 'Picked Up',
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
                FilamentTableActions::view()
                    ->url(fn (JewelleryOrderListing $record): string => $record->isSell()
                        ? static::getUrl('view-sell', ['record' => $record->source_id])
                        : static::getUrl('view', ['record' => $record->source_id])),
                FilamentTableActions::edit()
                    ->url(fn (JewelleryOrderListing $record): string => $record->isSell()
                        ? static::getUrl('edit-sell', ['record' => $record->source_id])
                        : static::getUrl('edit', ['record' => $record->source_id])),
                FilamentTableActions::make('assign_driver')
                    ->icon('heroicon-o-user-plus')
                    ->color('info')
                    ->tooltip('Assign Driver')
                    ->visible(fn (JewelleryOrderListing $record): bool => ! in_array($record->status, ['completed', 'cancelled', 'failed'], true))
                    ->form(fn (JewelleryOrderListing $record): array => [
                        ($record->isSell()
                            ? static::sellDriverAssignmentSelect($record->driver_id)
                            : static::driverAssignmentSelect($record->driver_id))
                            ->label('Driver')
                            ->required(),
                    ])
                    ->action(function (JewelleryOrderListing $record, array $data): void {
                        if ($record->isSell()) {
                            $booking = OldGoldBooking::query()->find($record->source_id);

                            if ($booking) {
                                $booking->update(['driver_id' => $data['driver_id']]);
                            }
                        } else {
                            $order = JewelleryOrder::query()->find($record->source_id);

                            if ($order) {
                                $order->update(['driver_id' => $data['driver_id']]);
                            }
                        }

                        Notification::make()
                            ->title('Driver assigned')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No jewellery orders yet')
            ->emptyStateDescription('Buy Now and Sell Jewellery requests from the mobile app will appear here.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJewelleryOrders::route('/'),
            'view' => Pages\ViewJewelleryOrder::route('/{record}'),
            'view-sell' => Pages\ViewSellJewelleryOrder::route('/sell/{record}'),
            'edit' => Pages\EditJewelleryOrder::route('/{record}/edit'),
            'edit-sell' => Pages\EditSellJewelleryOrder::route('/sell/{record}/edit'),
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

    public static function sellForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Sell Request Details')
                    ->schema([
                        Forms\Components\TextInput::make('booking_number')
                            ->label('Request #')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'name')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('item_name')
                            ->label('Item')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                        Forms\Components\Select::make('status')
                            ->options(config('sell_jewellery.statuses', []))
                            ->required(),
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Admin Notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),
                Forms\Components\Section::make('Metal & Valuation')
                    ->schema([
                        Forms\Components\TextInput::make('metal_type')
                            ->label('Metal Type')
                            ->formatStateUsing(fn (?string $state): string => SellJewelleryPayload::metalLabel($state))
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('purity')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('estimated_weight_grams')
                            ->label('Weight (g)')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('rate_per_gram')
                            ->label('Rate / g')
                            ->prefix('₹')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('quoted_amount')
                            ->label('Estimated Value')
                            ->prefix('₹')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('final_amount')
                            ->label('Final Amount')
                            ->prefix('₹')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01),
                    ])->columns(3),
                Forms\Components\Section::make('Customer & Pickup')
                    ->schema([
                        Forms\Components\TextInput::make('identity_owner')
                            ->label('Identity Owner')
                            ->formatStateUsing(fn (?string $state): string => SellJewelleryPayload::identityOwnerLabel($state))
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('sell_location')
                            ->label('Sell Location')
                            ->formatStateUsing(fn (?string $state): string => SellJewelleryPayload::sellLocationLabel($state))
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('pickup_name')
                            ->label('Contact Name')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('pickup_phone')
                            ->label('Contact Phone')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Textarea::make('pickup_address')
                            ->label('Pickup Address')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ])->columns(2),
                Forms\Components\Section::make('Pickup Scheduling')
                    ->schema([
                        static::sellDriverAssignmentSelect(),
                        Forms\Components\DateTimePicker::make('driver_assigned_at')
                            ->label('Driver Assigned At')
                            ->disabled()
                            ->dehydrated(false)
                            ->native(false)
                            ->visible(fn (Forms\Get $get): bool => filled($get('driver_id'))),
                        Forms\Components\DateTimePicker::make('pickup_scheduled_at')
                            ->label('Pickup Scheduled At')
                            ->native(false),
                        Forms\Components\DateTimePicker::make('accepted_at')
                            ->label('Accepted At')
                            ->disabled()
                            ->dehydrated(false)
                            ->native(false),
                        Forms\Components\DateTimePicker::make('picked_up_at')
                            ->label('Picked Up At')
                            ->native(false),
                        Forms\Components\DateTimePicker::make('completed_at')
                            ->label('Completed At')
                            ->native(false),
                        Forms\Components\TextInput::make('delivery_otp')
                            ->label('Delivery OTP')
                            ->disabled()
                            ->dehydrated(false),
                    ])->columns(2),
                Forms\Components\Section::make('Uploaded Documents')
                    ->schema([
                        Forms\Components\Placeholder::make('documents_list')
                            ->label('')
                            ->content(function (?OldGoldBooking $record): string {
                                if (! $record) {
                                    return '—';
                                }

                                return collect(SellJewelleryPayload::documents($record))
                                    ->map(function (array $doc): string {
                                        if (! $doc['uploaded'] || blank($doc['url'])) {
                                            return $doc['label'].': Not uploaded';
                                        }

                                        return $doc['label'].': '.$doc['url'];
                                    })
                                    ->implode("\n") ?: '—';
                            })
                            ->columnSpanFull(),
                    ]),
            ]);
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

    public static function sellDriverAssignmentSelect(?int $includeDriverId = null): Forms\Components\Select
    {
        return Forms\Components\Select::make('driver_id')
            ->label('Assigned Driver')
            ->options(function (?OldGoldBooking $record, Forms\Get $get) use ($includeDriverId): array {
                return Driver::assignmentOptions(
                    $includeDriverId ?? $record?->driver_id ?? $get('driver_id'),
                );
            })
            ->placeholder('Select a driver')
            ->nullable()
            ->live()
            ->afterStateUpdated(function (?int $state, OldGoldBooking $record, Forms\Set $set, $livewire): void {
                if (! $livewire instanceof Pages\ViewSellJewelleryOrder) {
                    return;
                }

                $record->update(['driver_id' => $state]);
                $record->refresh();

                $set('driver_assigned_at', $record->driver_assigned_at);

                Notification::make()
                    ->title(filled($state) ? 'Driver assigned to sell request' : 'Driver unassigned from sell request')
                    ->success()
                    ->send();
            });
    }
}
