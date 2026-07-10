<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Filament\Resources\JewelleryEmiPlanResource\Pages;
use App\Models\JewelleryEmiPlan;
use App\Support\FilamentTableActions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class JewelleryEmiPlanResource extends Resource
{
    use InteractsWithAdminPermissions;

    protected static ?string $model = JewelleryEmiPlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Jewellery Management';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'EMI Plans';

    protected static ?string $modelLabel = 'EMI Plan';

    protected static ?string $pluralModelLabel = 'EMI Plans';

    protected static function adminPermissionModule(): string
    {
        return 'jewellery_emi_plans';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('EMI Plan')
                    ->schema([
                        Forms\Components\TextInput::make('tenure_months')
                            ->label('Tenure (months)')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(60)
                            ->unique(ignoreRecord: true)
                            ->helperText('Number of monthly installments.'),
                        Forms\Components\TextInput::make('interest_rate_percent')
                            ->label('Interest Rate (%)')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->default(0)
                            ->suffix('%')
                            ->helperText('Annual interest rate used to calculate total EMI cost.'),
                        Forms\Components\TextInput::make('min_order_amount')
                            ->label('Minimum Order Amount')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('₹')
                            ->helperText('Leave empty if this plan applies to all order amounts.'),
                        Forms\Components\TextInput::make('label')
                            ->label('Display Label')
                            ->maxLength(100)
                            ->helperText('Optional label shown in the app, e.g. "6 months No Cost EMI".'),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
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
                Tables\Columns\TextColumn::make('tenure_months')
                    ->label('Tenure')
                    ->formatStateUsing(fn (int $state): string => $state.' month'.($state === 1 ? '' : 's'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('label')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('interest_rate_percent')
                    ->label('Interest')
                    ->suffix('%')
                    ->sortable(),
                Tables\Columns\TextColumn::make('min_order_amount')
                    ->label('Min Order')
                    ->inr()
                    ->placeholder('Any')
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Sort')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
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
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJewelleryEmiPlans::route('/'),
            'create' => Pages\CreateJewelleryEmiPlan::route('/create'),
            'view' => Pages\ViewJewelleryEmiPlan::route('/{record}'),
            'edit' => Pages\EditJewelleryEmiPlan::route('/{record}/edit'),
        ];
    }
}
