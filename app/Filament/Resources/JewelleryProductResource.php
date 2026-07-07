<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Filament\Resources\JewelleryProductResource\Pages;
use App\Models\JewelleryProduct;
use App\Models\JewellerySubCategory;
use App\Models\JewelleryCategory;
use App\Support\FilamentTableActions;
use App\Support\FilamentFormat;
use App\Support\JewelleryPricing;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class JewelleryProductResource extends Resource
{
    use InteractsWithAdminPermissions;

    protected static function adminPermissionModule(): string
    {
        return 'jewellery_products';
    }

    protected static ?string $model = JewelleryProduct::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'Jewellery Management';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Products';

    protected static ?string $modelLabel = 'Jewellery Product';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Product Details')
                    ->description('Products appear in the user app under Gold / Silver tabs.')
                    ->schema([
                        Forms\Components\Select::make('metal_type')
                            ->options(['gold' => 'Gold', 'silver' => 'Silver'])
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => static::syncSellingPrice($set, $get)),
                        Forms\Components\Select::make('jewellery_category_id')
                            ->label('Category')
                            ->options(function (Forms\Get $get): array {
                                return JewelleryCategory::query()
                                    ->where('is_active', true)
                                    ->when(
                                        filled($get('metal_type')),
                                        fn (Builder $query) => $query->where(function (Builder $inner) use ($get): void {
                                            $inner->where('metal_type', $get('metal_type'))
                                                ->orWhere('metal_type', 'both');
                                        })
                                    )
                                    ->orderBy('sort_order')
                                    ->pluck('name', 'id')
                                    ->all();
                            })
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('jewellery_sub_category_id', null)),
                        Forms\Components\Select::make('jewellery_sub_category_id')
                            ->label('Sub Category (optional)')
                            ->options(fn (Forms\Get $get): array => JewellerySubCategory::query()
                                ->where('is_active', true)
                                ->when(
                                    filled($get('jewellery_category_id')),
                                    fn (Builder $q) => $q->where('jewellery_category_id', $get('jewellery_category_id'))
                                )
                                ->orderBy('sort_order')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->nullable(),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('sku')
                            ->label('SKU')
                            ->readOnly()
                            ->maxLength(64)
                            ->hiddenOn('create')
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('purity')
                            ->placeholder('22K')
                            ->maxLength(20),
                        Forms\Components\TextInput::make('weight_grams')
                            ->label('Weight (grams)')
                            ->numeric()
                            ->step(0.001)
                            ->suffix('g')
                            ->live(debounce: 400)
                            ->afterStateUpdated(fn (Set $set, Get $get) => static::syncSellingPrice($set, $get)),
                        Forms\Components\FileUpload::make('image')
                            ->image()
                            ->disk('public')
                            ->directory('jewellery/products')
                            ->visibility('public')
                            ->required()
                            ->maxSize(4096)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Pricing & Visibility')
                    ->schema([
                        Forms\Components\Placeholder::make('pricing_breakdown')
                            ->label('Price Calculation')
                            ->content(fn (Get $get): HtmlString => static::pricingBreakdownHtml($get))
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('making_charge_percent')
                            ->label('Making Charge')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->suffix('%')
                            ->live(debounce: 400)
                            ->afterStateUpdated(fn (Set $set, Get $get) => static::syncSellingPrice($set, $get))
                            ->helperText('Optional percentage added on top of metal value.'),
                        Forms\Components\TextInput::make('price')
                            ->label('Selling Price (Total)')
                            ->numeric()
                            ->prefix('₹')
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Auto-calculated from weight × current rate + making charge.'),
                        Forms\Components\Select::make('stock_status')
                            ->options([
                                'in_stock' => 'In Stock',
                                'out_of_stock' => 'Out of Stock',
                                'sold_out' => 'Sold Out',
                                'coming_soon' => 'Coming Soon',
                            ])
                            ->default('in_stock')
                            ->required(),
                        Forms\Components\TextInput::make('sort_order')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Show in App')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('Image')
                    ->disk('public')
                    ->visibility('public')
                    ->square()
                    ->size(56)
                    ->checkFileExistence(false),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('metal_type')->badge()
                    ->colors(['warning' => 'gold', 'gray' => 'silver']),
                Tables\Columns\TextColumn::make('category.name')->label('Category'),
                Tables\Columns\TextColumn::make('subCategory.name')->label('Sub Category')->placeholder('—'),
                Tables\Columns\TextColumn::make('purity')->placeholder('—'),
                Tables\Columns\TextColumn::make('weight_grams')->suffix(' g')->placeholder('—'),
                Tables\Columns\TextColumn::make('price')->inr()->label('Total Price'),
                Tables\Columns\TextColumn::make('making_charge_percent')
                    ->label('Making %')
                    ->suffix('%')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('stock_status')->badge(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('App'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('metal_type')
                    ->options(['gold' => 'Gold', 'silver' => 'Silver']),
                Tables\Filters\SelectFilter::make('jewellery_category_id')
                    ->label('Category')
                    ->relationship('category', 'name'),
                Tables\Filters\SelectFilter::make('stock_status')
                    ->options([
                        'in_stock' => 'In Stock',
                        'out_of_stock' => 'Out of Stock',
                        'sold_out' => 'Sold Out',
                        'coming_soon' => 'Coming Soon',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')->label('Active in App'),
            ])
            ->actions([
                FilamentTableActions::edit(),
                FilamentTableActions::delete(),
            ])
            ->actionsColumnLabel('Actions')
            ->defaultSort('sort_order')
            ->reorderable('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJewelleryProducts::route('/'),
            'create' => Pages\CreateJewelleryProduct::route('/create'),
            'edit' => Pages\EditJewelleryProduct::route('/{record}/edit'),
        ];
    }

    protected static function syncSellingPrice(Set $set, Get $get): void
    {
        $pricing = JewelleryPricing::calculate(
            $get('metal_type'),
            $get('weight_grams'),
            $get('making_charge_percent'),
        );

        $set('price', $pricing['total']);
    }

    protected static function pricingBreakdownHtml(Get $get): HtmlString
    {
        $pricing = JewelleryPricing::calculate(
            $get('metal_type'),
            $get('weight_grams'),
            $get('making_charge_percent'),
        );

        if ($pricing['rate_per_gram'] === null) {
            return new HtmlString('<p class="text-sm text-gray-500">Select metal type and enter weight to calculate price.</p>');
        }

        $metalLabel = ucfirst((string) $get('metal_type'));
        $weight = number_format((float) $get('weight_grams'), 3);
        $lines = [
            sprintf('%s rate: %s/g', $metalLabel, FilamentFormat::inr($pricing['rate_per_gram'])),
            sprintf(
                'Metal value (%s g × %s): %s',
                $weight,
                FilamentFormat::inr($pricing['rate_per_gram']),
                FilamentFormat::inr($pricing['metal_value']),
            ),
        ];

        if ($pricing['making_charge_percent'] > 0) {
            $lines[] = sprintf(
                'Making charge (%s%%): %s',
                number_format($pricing['making_charge_percent'], 2),
                FilamentFormat::inr($pricing['making_charge_amount']),
            );
        }

        $lines[] = sprintf('<strong>Total: %s</strong>', FilamentFormat::inr($pricing['total']));

        return new HtmlString(
            '<div class="space-y-1 text-sm text-gray-700 dark:text-gray-300">'
            .implode('<br>', $lines)
            .'</div>'
        );
    }
}
