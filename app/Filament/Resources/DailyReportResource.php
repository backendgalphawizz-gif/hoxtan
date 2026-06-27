<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DailyReportResource\Pages;
use App\Models\DailyReport;
use App\Support\FilamentDateFilters;
use App\Support\FilamentTableActions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DailyReportResource extends Resource
{
    protected static ?string $model = DailyReport::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Dashboard';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Daily Reports';

    protected static ?string $modelLabel = 'Daily Report';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Report Summary')
                    ->schema([
                        Forms\Components\DatePicker::make('report_date')
                            ->disabled(),
                        Forms\Components\TextInput::make('new_users')
                            ->numeric()
                            ->disabled(),
                        Forms\Components\TextInput::make('active_investors')
                            ->numeric()
                            ->disabled(),
                        Forms\Components\TextInput::make('gold_holdings_total')
                            ->label('Gold Holdings Total (g)')
                            ->disabled(),
                        Forms\Components\TextInput::make('silver_holdings_total')
                            ->label('Silver Holdings Total (g)')
                            ->disabled(),
                        Forms\Components\TextInput::make('revenue_total')
                            ->prefix('₹')
                            ->disabled(),
                        Forms\Components\TextInput::make('transaction_count')
                            ->numeric()
                            ->disabled(),
                        Forms\Components\TextInput::make('gst_collected')
                            ->prefix('₹')
                            ->disabled(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('report_date')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('new_users')
                    ->label('New Users')
                    ->sortable(),
                Tables\Columns\TextColumn::make('active_investors')
                    ->label('Active Investors')
                    ->sortable(),
                Tables\Columns\TextColumn::make('gold_holdings_total')
                    ->label('Gold (g)')
                    ->grams(4),
                Tables\Columns\TextColumn::make('silver_holdings_total')
                    ->label('Silver (g)')
                    ->grams(4),
                Tables\Columns\TextColumn::make('revenue_total')
                    ->inr()
                    ->sortable(),
                Tables\Columns\TextColumn::make('transaction_count')
                    ->label('Transactions')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('gst_collected')
                    ->inr()
                    ->sortable(),
            ])
            ->filters([
                FilamentDateFilters::tableFilter('report_date', 'report_date', 'Report Date'),
            ])
            ->actions([
                FilamentTableActions::view(),
            ])
            ->bulkActions([])
            ->defaultSort('report_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDailyReports::route('/'),
            'view' => Pages\ViewDailyReport::route('/{record}'),
        ];
    }
}
