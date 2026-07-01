<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Filament\Resources\InvestmentGoalResource\Pages;
use App\Models\InvestmentGoal;
use App\Support\FilamentDateFilters;
use App\Support\FilamentTableActions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InvestmentGoalResource extends Resource
{
    use InteractsWithAdminPermissions;

    protected static function adminPermissionModule(): string
    {
        return 'investment_goals';
    }

    protected static ?string $model = InvestmentGoal::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationGroup = 'Investment Management';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Goal Management';

    protected static ?string $modelLabel = 'Investment Goal';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Goal Details')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('metal_type')
                            ->options(['gold' => 'Gold', 'silver' => 'Silver'])
                            ->required(),
                        Forms\Components\TextInput::make('target_grams')
                            ->label('Target (grams)')
                            ->required()
                            ->numeric()
                            ->minValue(0.0001)
                            ->step(0.0001)
                            ->suffix('g'),
                        Forms\Components\TextInput::make('current_grams')
                            ->label('Current (grams)')
                            ->numeric()
                            ->suffix('g')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Updated automatically from user holdings.'),
                        Forms\Components\TextInput::make('target_amount')
                            ->label('Target Amount')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->prefix('₹'),
                        Forms\Components\DatePicker::make('target_date')
                            ->native(false)
                            ->minDate(now())
                            ->rules(['nullable', 'date', 'after_or_equal:today']),
                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->required()
                            ->default('active'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('metal_type')
                    ->colors(['warning' => 'gold', 'gray' => 'silver']),
                Tables\Columns\TextColumn::make('target_grams')
                    ->label('Target (g)')
                    ->grams(4)
                    ->sortable(),
                Tables\Columns\TextColumn::make('current_grams')
                    ->label('Current (g)')
                    ->grams(4),
                Tables\Columns\TextColumn::make('target_amount')
                    ->inr()
                    ->sortable(),
                Tables\Columns\TextColumn::make('target_date')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'active',
                        'info' => 'completed',
                        'danger' => 'cancelled',
                    ]),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('metal_type')
                    ->options(['gold' => 'Gold', 'silver' => 'Silver']),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
                Tables\Filters\SelectFilter::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->label('User'),
                FilamentDateFilters::tableFilter('target_date', 'target_date', 'Target Date', allowFuture: true),
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
            'index' => Pages\ListInvestmentGoals::route('/'),
            'create' => Pages\CreateInvestmentGoal::route('/create'),
            'view' => Pages\ViewInvestmentGoal::route('/{record}'),
            'edit' => Pages\EditInvestmentGoal::route('/{record}/edit'),
        ];
    }
}
