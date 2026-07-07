<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Filament\Resources\JewellerySubCategoryResource\Pages;
use App\Models\JewellerySubCategory;
use App\Support\FilamentTableActions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class JewellerySubCategoryResource extends Resource
{
    use InteractsWithAdminPermissions;

    protected static function adminPermissionModule(): string
    {
        return 'jewellery_sub_categories';
    }

    protected static ?string $model = JewellerySubCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationGroup = 'Jewellery Management';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Sub Categories';

    protected static ?string $modelLabel = 'Jewellery Sub Category';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Sub Category Details')
                    ->schema([
                        Forms\Components\Select::make('jewellery_category_id')
                            ->label('Parent Category')
                            ->relationship('category', 'name')
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
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
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
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->toggleable(),
                Tables\Columns\TextColumn::make('products_count')
                    ->counts('products')
                    ->label('Products'),
                Tables\Columns\TextColumn::make('sort_order')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('jewellery_category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
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
            'index' => Pages\ListJewellerySubCategories::route('/'),
            'create' => Pages\CreateJewellerySubCategory::route('/create'),
            'edit' => Pages\EditJewellerySubCategory::route('/{record}/edit'),
        ];
    }
}
