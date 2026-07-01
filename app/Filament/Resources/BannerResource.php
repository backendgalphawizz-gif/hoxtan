<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Filament\Resources\BannerResource\Pages;
use App\Models\Banner;
use App\Support\FilamentDateFilters;
use App\Support\FilamentTableActions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BannerResource extends Resource
{
    use InteractsWithAdminPermissions;

    protected static function adminPermissionModule(): string
    {
        return 'banners';
    }

    protected static ?string $model = Banner::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'CMS Management';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Banner Management';

    protected static ?string $modelLabel = 'Banner';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Banner Details')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\FileUpload::make('image')
                            ->image()
                            ->disk('public')
                            ->directory('banners')
                            ->visibility('public')
                            ->required()
                            ->maxSize(2048),
                        Forms\Components\TextInput::make('link')
                            ->url()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('sort_order')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                        Forms\Components\DateTimePicker::make('starts_at')
                            ->native(false)
                            ->live()
                            ->rules(['nullable', 'date']),
                        Forms\Components\DateTimePicker::make('ends_at')
                            ->native(false)
                            ->minDate(fn (Forms\Get $get) => $get('starts_at') ? \Carbon\Carbon::parse($get('starts_at')) : null)
                            ->after('starts_at')
                            ->rules(['nullable', 'date', 'after:starts_at']),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->disk('public')
                    ->visibility('public')
                    ->square()
                    ->size(56)
                    ->checkFileExistence(false),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('link')
                    ->limit(30)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                Tables\Columns\TextColumn::make('starts_at')
                    ->dateTime('d M Y H:i')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->dateTime('d M Y H:i')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
                FilamentDateFilters::tableFilter('validity_period', 'starts_at', 'Validity Period', allowFuture: true),
            ])
            ->actions([
                FilamentTableActions::view(),
                FilamentTableActions::edit(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBanners::route('/'),
            'create' => Pages\CreateBanner::route('/create'),
            'view' => Pages\ViewBanner::route('/{record}'),
            'edit' => Pages\EditBanner::route('/{record}/edit'),
        ];
    }
}
