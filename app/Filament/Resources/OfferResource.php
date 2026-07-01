<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Filament\Resources\OfferResource\Pages;
use App\Models\Offer;
use App\Support\FilamentDateFilters;
use App\Support\FilamentTableActions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OfferResource extends Resource
{
    use InteractsWithAdminPermissions;

    protected static function adminPermissionModule(): string
    {
        return 'offers';
    }

    protected static ?string $model = Offer::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'CMS Management';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Offers Management';

    protected static ?string $modelLabel = 'Offer';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Offer Details')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->maxLength(2000)
                            ->columnSpanFull(),
                        Forms\Components\FileUpload::make('image')
                            ->image()
                            ->disk('public')
                            ->directory('offers')
                            ->visibility('public')
                            ->maxSize(2048),
                        Forms\Components\Select::make('discount_type')
                            ->options([
                                'percentage' => 'Percentage',
                                'flat' => 'Flat Amount',
                            ])
                            ->required()
                            ->default('percentage')
                            ->live(),
                        Forms\Components\TextInput::make('discount_value')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->suffix(fn (Forms\Get $get) => $get('discount_type') === 'percentage' ? '%' : '₹')
                            ->rules([
                                fn (Forms\Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                                    if ($get('discount_type') === 'percentage' && $value > 100) {
                                        $fail('Percentage discount cannot exceed 100%.');
                                    }
                                },
                            ]),
                        Forms\Components\TextInput::make('promo_code')
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->alphaDash(),
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
                    ->checkFileExistence(false)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('promo_code')
                    ->searchable()
                    ->copyable()
                    ->badge(),
                Tables\Columns\BadgeColumn::make('discount_type')
                    ->colors(['primary' => 'percentage', 'success' => 'flat']),
                Tables\Columns\TextColumn::make('discount_value')
                    ->formatStateUsing(fn ($state, Offer $record) => $record->discount_type === 'percentage'
                        ? $state.'%'
                        : '₹'.number_format($state, 2)),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                Tables\Columns\TextColumn::make('starts_at')
                    ->dateTime('d M Y')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->dateTime('d M Y')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
                Tables\Filters\SelectFilter::make('discount_type')
                    ->options(['percentage' => 'Percentage', 'flat' => 'Flat Amount']),
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
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOffers::route('/'),
            'create' => Pages\CreateOffer::route('/create'),
            'view' => Pages\ViewOffer::route('/{record}'),
            'edit' => Pages\EditOffer::route('/{record}/edit'),
        ];
    }
}
