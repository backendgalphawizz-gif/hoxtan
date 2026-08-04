<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Filament\Resources\JewellerySubSubCategoryResource\Pages;
use App\Models\JewellerySubSubCategory;
use App\Support\FilamentTableActions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class JewellerySubSubCategoryResource extends Resource
{
    use InteractsWithAdminPermissions;

    protected static function adminPermissionModule(): string
    {
        return 'jewellery_sub_sub_categories';
    }

    protected static ?string $model = JewellerySubSubCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $navigationGroup = 'Jewellery Management';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Sub Sub Categories';

    protected static ?string $modelLabel = 'Jewellery Sub Sub Category';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Sub Sub Category Details')
                    ->schema([
                        Forms\Components\Select::make('jewellery_sub_category_id')
                            ->label('Parent Sub Category')
                            ->relationship('subCategory', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', Str::slug((string) $state))),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sort order')
                            ->numeric()
                            ->minValue(1)
                            ->default(function (Forms\Get $get): int {
                                $parentId = $get('jewellery_sub_category_id');
                                $model = new JewellerySubSubCategory(['jewellery_sub_category_id' => $parentId]);

                                return JewellerySubSubCategory::nextSortOrder($model);
                            })
                            ->required()
                            ->helperText('Sequence within the parent. Changing to a taken number swaps positions.'),
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
                Tables\Columns\TextColumn::make('subCategory.category.name')
                    ->label('Category')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subCategory.name')
                    ->label('Sub Category')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->toggleable(),
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
                Tables\Filters\SelectFilter::make('jewellery_sub_category_id')
                    ->label('Sub Category')
                    ->relationship('subCategory', 'name')
                    ->searchable()
                    ->preload(),
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
            'index' => Pages\ListJewellerySubSubCategories::route('/'),
            'create' => Pages\CreateJewellerySubSubCategory::route('/create'),
            'edit' => Pages\EditJewellerySubSubCategory::route('/{record}/edit'),
        ];
    }
}
