<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Filament\Resources\JewelleryCategoryResource\Pages;
use App\Models\JewelleryCategory;
use App\Support\FilamentTableActions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class JewelleryCategoryResource extends Resource
{
    use InteractsWithAdminPermissions;

    protected static function adminPermissionModule(): string
    {
        return 'jewellery_categories';
    }

    protected static ?string $model = JewelleryCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Jewellery Management';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Categories';

    protected static ?string $modelLabel = 'Jewellery Category';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Category Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', Str::slug((string) $state))),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\Select::make('metal_type')
                            ->options([
                                'gold' => 'Gold',
                                'silver' => 'Silver',
                                'both' => 'Gold & Silver',
                            ])
                            ->default('both')
                            ->required(),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sort order')
                            ->numeric()
                            ->minValue(1)
                            ->default(fn (): int => JewelleryCategory::nextSortOrder())
                            ->required()
                            ->helperText('Sequence position (1, 2, 3…). If you move to a taken number, the other item swaps places.'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->toggleable(),
                Tables\Columns\TextColumn::make('metal_type')->badge()
                    ->colors(['warning' => 'gold', 'gray' => 'silver', 'info' => 'both']),
                Tables\Columns\TextColumn::make('sub_categories_count')
                    ->counts('subCategories')
                    ->label('Sub-categories'),
                Tables\Columns\TextColumn::make('products_count')
                    ->counts('products')
                    ->label('Products'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Sort order')
                    ->sortable()
                    ->alignCenter(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('metal_type')
                    ->options(['gold' => 'Gold', 'silver' => 'Silver', 'both' => 'Both']),
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
            ])
            ->actions([
                FilamentTableActions::edit(),
                FilamentTableActions::delete(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJewelleryCategories::route('/'),
            'create' => Pages\CreateJewelleryCategory::route('/create'),
            'edit' => Pages\EditJewelleryCategory::route('/{record}/edit'),
        ];
    }
}
