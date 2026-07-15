<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Filament\Resources\JewelleryProductResource\Pages;
use App\Models\JewelleryProduct;
use App\Models\JewellerySubCategory;
use App\Models\JewellerySubSubCategory;
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

    protected static ?int $navigationSort = 4;

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
                            ->afterStateUpdated(function (Set $set, Get $get): void {
                                if ((bool) $get('has_size_variants')) {
                                    static::syncVariantPrices($set, $get);

                                    return;
                                }

                                static::syncSellingPrice($set, $get);
                            }),
                        Forms\Components\Select::make('gender')
                            ->label('Gender (Filters: Men\'s / Women\'s)')
                            ->options([
                                'men' => "Men's",
                                'women' => "Women's",
                                'unisex' => 'Unisex',
                            ])
                            ->nullable()
                            ->placeholder('Select gender'),
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
                            ->afterStateUpdated(function (Forms\Set $set): void {
                                $set('jewellery_sub_category_id', null);
                                $set('jewellery_sub_sub_category_id', null);
                            }),
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
                            ->nullable()
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('jewellery_sub_sub_category_id', null)),
                        Forms\Components\Select::make('jewellery_sub_sub_category_id')
                            ->label('Sub Sub Category (optional)')
                            ->options(fn (Forms\Get $get): array => JewellerySubSubCategory::query()
                                ->where('is_active', true)
                                ->when(
                                    filled($get('jewellery_sub_category_id')),
                                    fn (Builder $q) => $q->where('jewellery_sub_category_id', $get('jewellery_sub_category_id'))
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
                        Forms\Components\Select::make('purity')
                            ->options(collect(config('jewellery.purities', []))->mapWithKeys(
                                fn (array $row) => [($row['value'] ?? '') => ($row['label'] ?? $row['value'] ?? '')]
                            )->filter()->all())
                            ->searchable()
                            ->nullable()
                            ->placeholder('Select purity'),
                        Forms\Components\Toggle::make('has_size_variants')
                            ->label('Enable size variants')
                            ->helperText('When enabled, set weight (and auto price) for each size. When off, use a single size/weight as before.')
                            ->default(false)
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get, ?bool $state): void {
                                if ($state) {
                                    $set('size', null);
                                    $set('weight_grams', null);
                                    $set('price', 0);
                                } else {
                                    $set('variants', []);
                                    static::syncSellingPrice($set, $get);
                                }
                            })
                            ->columnSpanFull(),
                        Forms\Components\Select::make('size')
                            ->label('Size (optional)')
                            ->options(JewelleryProduct::sizeOptions())
                            ->searchable()
                            ->nullable()
                            ->placeholder('Select size')
                            ->visible(fn (Get $get): bool => ! (bool) $get('has_size_variants')),
                        Forms\Components\TextInput::make('weight_grams')
                            ->label('Weight (grams)')
                            ->numeric()
                            ->step(0.001)
                            ->suffix('g')
                            ->required(fn (Get $get): bool => ! (bool) $get('has_size_variants'))
                            ->visible(fn (Get $get): bool => ! (bool) $get('has_size_variants'))
                            ->live(debounce: 400)
                            ->afterStateUpdated(fn (Set $set, Get $get) => static::syncSellingPrice($set, $get)),
                        Forms\Components\Repeater::make('variants')
                            ->relationship()
                            ->label('Size variants')
                            ->helperText('Each size has its own weight. Price is calculated from live metal rate + making charge − discount.')
                            ->visible(fn (Get $get): bool => (bool) $get('has_size_variants'))
                            ->required(fn (Get $get): bool => (bool) $get('has_size_variants'))
                            ->minItems(fn (Get $get): int => (bool) $get('has_size_variants') ? 1 : 0)
                            ->defaultItems(0)
                            ->collapsible()
                            ->reorderable()
                            ->orderColumn('sort_order')
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get): void {
                                static::syncVariantPrices($set, $get);
                            })
                            ->schema([
                                Forms\Components\Select::make('size')
                                    ->label('Size')
                                    ->options(JewelleryProduct::sizeOptions())
                                    ->searchable()
                                    ->required()
                                    ->distinct()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                                Forms\Components\TextInput::make('weight_grams')
                                    ->label('Weight (grams)')
                                    ->numeric()
                                    ->step(0.001)
                                    ->suffix('g')
                                    ->required()
                                    ->minValue(0.001)
                                    ->live(debounce: 300)
                                    ->afterStateUpdated(function (Set $set, Get $get): void {
                                        $set('price', static::variantPriceForItem($get));
                                    }),
                                Forms\Components\Placeholder::make('price_display')
                                    ->label('Price')
                                    ->content(function (Get $get): HtmlString {
                                        $total = static::variantPriceForItem($get);

                                        return new HtmlString(
                                            '<div class="text-sm font-semibold text-gray-900 dark:text-gray-100">'
                                            .e(FilamentFormat::inr($total))
                                            .'</div>'
                                        );
                                    }),
                                Forms\Components\Hidden::make('price')
                                    ->default(0)
                                    ->dehydrated()
                                    ->dehydrateStateUsing(fn ($state, Get $get) => static::variantPriceForItem($get)),
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true)
                                    ->inline(false),
                            ])
                            ->columns(4)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Product Images')
                    ->description('Upload up to 5 images (size 1000*1000). Drag to reorder — the first image is the cover shown in the app.')
                    ->schema([
                        static::productImagesField(),
                    ]),

                Forms\Components\Section::make('Pricing & Visibility')
                    ->schema([
                        Forms\Components\Placeholder::make('pricing_breakdown')
                            ->label('Price Calculation')
                            ->content(function (Get $get): HtmlString {
                                if ((bool) $get('has_size_variants')) {
                                    return new HtmlString(
                                        '<p class="text-sm text-gray-500">Size variants enabled — each size uses its own weight. Prices update from live metal rates + making charge − discount.</p>'
                                    );
                                }

                                return static::pricingBreakdownHtml($get);
                            })
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('making_charge_percent')
                            ->label('Making Charge')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->suffix('%')
                            ->live(debounce: 400)
                            ->afterStateUpdated(function (Set $set, Get $get): void {
                                if ((bool) $get('has_size_variants')) {
                                    static::syncVariantPrices($set, $get);

                                    return;
                                }

                                static::syncSellingPrice($set, $get);
                            })
                            ->helperText('Optional percentage added on top of metal value.'),
                        Forms\Components\Select::make('discount_type')
                            ->label('Discount Type')
                            ->options([
                                'percent' => 'Percent (%)',
                                'flat' => 'Flat (₹)',
                            ])
                            ->nullable()
                            ->placeholder('No discount')
                            ->helperText('Discount applies only on making charge, not on metal value.')
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                                if (! filled($state)) {
                                    $set('discount_value', null);
                                }

                                if ((bool) $get('has_size_variants')) {
                                    static::syncVariantPrices($set, $get);

                                    return;
                                }

                                static::syncSellingPrice($set, $get);
                            }),
                        Forms\Components\TextInput::make('discount_value')
                            ->label('Discount Value')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->visible(fn (Get $get): bool => filled($get('discount_type')))
                            ->suffix(fn (Get $get): string => $get('discount_type') === 'percent' ? '%' : '₹')
                            ->maxValue(fn (Get $get): ?float => $get('discount_type') === 'percent' ? 100 : null)
                            ->helperText('Percent or flat amount off the making charge only.')
                            ->live(debounce: 400)
                            ->afterStateUpdated(function (Set $set, Get $get): void {
                                if ((bool) $get('has_size_variants')) {
                                    static::syncVariantPrices($set, $get);

                                    return;
                                }

                                static::syncSellingPrice($set, $get);
                            }),
                        Forms\Components\TextInput::make('price')
                            ->label('Selling Price (Total)')
                            ->numeric()
                            ->prefix('₹')
                            ->disabled()
                            ->dehydrated()
                            ->default(0)
                            ->hidden(fn (Get $get): bool => (bool) $get('has_size_variants'))
                            ->helperText('Metal value + making charge − discount on making charge.'),
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
                    ->getStateUsing(fn (JewelleryProduct $record): ?string => $record->resolvedImagePath())
                    ->square()
                    ->size(56)
                    ->checkFileExistence(false),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('metal_type')->badge()
                    ->colors(['warning' => 'gold', 'gray' => 'silver']),
                Tables\Columns\TextColumn::make('category.name')->label('Category'),
                Tables\Columns\TextColumn::make('subCategory.name')->label('Sub Category')->placeholder('—'),
                Tables\Columns\TextColumn::make('subSubCategory.name')->label('Sub Sub Category')->placeholder('—'),
                Tables\Columns\TextColumn::make('purity')->placeholder('—'),
                Tables\Columns\TextColumn::make('size')
                    ->placeholder('—')
                    ->formatStateUsing(function ($state, JewelleryProduct $record): string {
                        if ($record->has_size_variants) {
                            $count = $record->variants()->count();

                            return $count > 0 ? $count.' sizes' : 'Variants';
                        }

                        return filled($state) ? (string) $state : '—';
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('weight_grams')
                    ->suffix(' g')
                    ->placeholder('—')
                    ->formatStateUsing(function ($state, JewelleryProduct $record): string {
                        if ($record->has_size_variants) {
                            return 'per size';
                        }

                        return $state !== null ? (string) $state : '—';
                    }),
                Tables\Columns\TextColumn::make('price')->inr()->label('Total Price'),
                Tables\Columns\TextColumn::make('making_charge_percent')
                    ->label('Making %')
                    ->suffix('%')
                    ->placeholder('—'),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('App'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('metal_type')
                    ->options(['gold' => 'Gold', 'silver' => 'Silver']),
                Tables\Filters\SelectFilter::make('jewellery_category_id')
                    ->label('Category')
                    ->relationship('category', 'name'),
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

    protected static function productImagesField(): Forms\Components\FileUpload
    {
        return Forms\Components\FileUpload::make('image')
            ->label('Images')
            ->image()
            ->multiple()
            ->minFiles(1)
            ->maxFiles(5)
            ->reorderable()
            ->appendFiles()
            ->panelLayout('grid')
            ->itemPanelAspectRatio(1)
            ->imagePreviewHeight('136')
            ->placeholder('Drag & drop images here, or click to browse')
            ->helperText('Image size: 1000*1000 px (square). Max 4 MB each.')
            ->imageEditor()
            ->imageEditorAspectRatios([
                '1:1' => 'Square (1:1)',
            ])
            ->imageCropAspectRatio('1:1')
            ->imageResizeMode('cover')
            ->imageResizeTargetWidth('1000')
            ->imageResizeTargetHeight('1000')
            ->disk('public')
            ->directory('jewellery/products')
            ->visibility('public')
            ->required()
            ->maxSize(4096)
            ->columnSpanFull();
    }

    protected static function syncSellingPrice(Set $set, Get $get): void
    {
        $pricing = static::pricingForForm($get);

        $set('price', $pricing['total']);
    }

    protected static function syncVariantPrices(Set $set, Get $get): void
    {
        $variants = $get('variants');

        if (! is_array($variants)) {
            return;
        }

        foreach ($variants as $uuid => $variant) {
            if (! is_array($variant)) {
                continue;
            }

            $pricing = JewelleryPricing::calculate(
                $get('metal_type'),
                $variant['weight_grams'] ?? null,
                $get('making_charge_percent'),
                $get('discount_type'),
                $get('discount_value'),
            );

            $variants[$uuid]['price'] = $pricing['total'];
        }

        $set('variants', $variants);
    }

    /**
     * Resolve parent form values from inside a repeater item (Filament nesting varies).
     */
    protected static function rootFormValue(Get $get, string $key): mixed
    {
        foreach ([$key, '../'.$key, '../../'.$key, '../../../'.$key] as $path) {
            $value = $get($path);

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    protected static function variantPriceForItem(Get $get): float
    {
        $pricing = JewelleryPricing::calculate(
            static::rootFormValue($get, 'metal_type'),
            $get('weight_grams'),
            static::rootFormValue($get, 'making_charge_percent'),
            static::rootFormValue($get, 'discount_type'),
            static::rootFormValue($get, 'discount_value'),
        );

        return (float) $pricing['total'];
    }

    /**
     * @return array{
     *     rate_per_gram: ?float,
     *     metal_value: float,
     *     making_charge_percent: float,
     *     making_charge_amount: float,
     *     subtotal_before_discount: float,
     *     discount_type: ?string,
     *     discount_value: float,
     *     discount_amount: float,
     *     total: float
     * }
     */
    protected static function pricingForForm(Get $get): array
    {
        return JewelleryPricing::calculate(
            $get('metal_type'),
            $get('weight_grams'),
            $get('making_charge_percent'),
            $get('discount_type'),
            $get('discount_value'),
        );
    }

    protected static function pricingBreakdownHtml(Get $get): HtmlString
    {
        $pricing = static::pricingForForm($get);

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

        if ($pricing['discount_amount'] > 0) {
            $discountLabel = $pricing['discount_type'] === 'percent'
                ? number_format($pricing['discount_value'], 2).'%'
                : FilamentFormat::inr($pricing['discount_value']);

            $lines[] = sprintf(
                'Discount on making charge (%s): -%s',
                $discountLabel,
                FilamentFormat::inr($pricing['discount_amount']),
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
