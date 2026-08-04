<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Models\Investment;
use App\Services\GstService;
use App\Services\MetalRateService;
use App\Support\FilamentDateFilters;
use App\Support\FilamentTableActions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

abstract class InvestmentResource extends Resource
{
    use InteractsWithAdminPermissions;

    protected static ?string $model = Investment::class;

    protected static ?string $navigationGroup = 'Investment Management';

    protected static bool $shouldRegisterNavigation = false;

    abstract protected static function transactionType(): string;

    abstract protected static function resolveNavigationLabel(): string;

    abstract protected static function resolveNavigationIcon(): string;

    public static function getNavigationLabel(): string
    {
        return static::resolveNavigationLabel();
    }

    public static function getNavigationIcon(): ?string
    {
        return static::resolveNavigationIcon();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', static::transactionType());
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Transaction Details')
                    ->schema([
                        Forms\Components\Hidden::make('type')->default(static::transactionType()),
                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->required(),
                        Forms\Components\Select::make('metal_type')
                            ->options(['gold' => 'Gold', 'silver' => 'Silver'])
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, ?string $state): void {
                                static::applyActiveMetalRate($set, $state);
                                static::recalculateFormAmounts($set, $get);
                            }),
                        Forms\Components\TextInput::make('quantity_grams')
                            ->label('Quantity (grams)')
                            ->required()
                            ->numeric()
                            ->minValue(0.0001)
                            ->step(0.0001)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) => static::recalculateFormAmounts($set, $get)),
                        Forms\Components\TextInput::make('rate_per_gram')
                            ->label('Rate per Gram')
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->prefix('₹')
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Auto-filled from the current active metal rate.'),
                        Forms\Components\TextInput::make('amount')
                            ->label('Base Amount')
                            ->numeric()
                            ->prefix('₹')
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\TextInput::make('gst_amount')
                            ->label(fn (): string => 'GST ('.app(GstService::class)->ratePercent().'%)')
                            ->numeric()
                            ->prefix('₹')
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\TextInput::make('total_amount')
                            ->label('Total Amount')
                            ->numeric()
                            ->prefix('₹')
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'completed' => 'Completed',
                                'failed' => 'Failed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->required()
                            ->default('pending'),
                        Forms\Components\Textarea::make('notes')->maxLength(500)->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference_id')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('user.name')->searchable()->sortable(),
                Tables\Columns\BadgeColumn::make('metal_type')->colors(['warning' => 'gold', 'gray' => 'silver']),
                Tables\Columns\TextColumn::make('quantity_grams')->label('Qty (g)')->grams(4),
                Tables\Columns\TextColumn::make('rate_per_gram')->inr(),
                Tables\Columns\TextColumn::make('total_amount')->inr()->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'completed',
                        'danger' => 'failed',
                        'gray' => 'cancelled',
                    ]),
                Tables\Columns\TextColumn::make('created_at')->dateTime('d M Y H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('metal_type')->options(['gold' => 'Gold', 'silver' => 'Silver']),
                Tables\Filters\SelectFilter::make('status')
                    ->options(['pending' => 'Pending', 'completed' => 'Completed', 'failed' => 'Failed', 'cancelled' => 'Cancelled']),
                FilamentDateFilters::tableFilter('transaction_date', 'created_at', 'Transaction Date'),
            ])
            ->actions([
                FilamentTableActions::view(),
                FilamentTableActions::edit(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function calculateAmounts(array $data): array
    {
        $quantity = (float) ($data['quantity_grams'] ?? 0);
        $rate = (float) ($data['rate_per_gram'] ?? 0);
        $amount = round($quantity * $rate, 2);
        $gst = app(GstService::class)->calculateGstAmount($amount);

        $data['amount'] = $amount;
        $data['gst_amount'] = $gst['gst_amount'];
        $data['total_amount'] = $gst['total'];

        return $data;
    }

    protected static function applyActiveMetalRate(Forms\Set $set, ?string $metalType): void
    {
        if (blank($metalType)) {
            return;
        }

        $rates = app(MetalRateService::class);
        $active = $rates->getActiveRate($metalType);
        $rate = $active?->rate_per_gram ?? $rates->getLiveRate($metalType);

        $set('rate_per_gram', $rate);
    }

    protected static function recalculateFormAmounts(Forms\Set $set, Forms\Get $get): void
    {
        $amounts = static::calculateAmounts([
            'quantity_grams' => $get('quantity_grams'),
            'rate_per_gram' => $get('rate_per_gram'),
        ]);

        $set('amount', $amounts['amount']);
        $set('gst_amount', $amounts['gst_amount']);
        $set('total_amount', $amounts['total_amount']);
    }
}
