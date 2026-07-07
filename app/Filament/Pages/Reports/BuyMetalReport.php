<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Exports\InvestmentReportExporter;
use App\Models\Investment;
use App\Support\FilamentDateFilters;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BuyMetalReport extends BaseReportPage
{
    protected static function adminPermissionModule(): string
    {
        return 'reports_buy_metal';
    }

    protected static ?string $title = 'Buy Gold / Silver Report';

    public function table(Table $table): Table
    {
        return $table
            ->query(Investment::query()->with('user')->where('type', 'buy'))
            ->columns([
                TextColumn::make('reference_id')->searchable()->copyable(),
                TextColumn::make('user.name')->searchable(),
                TextColumn::make('metal_type')->badge(),
                TextColumn::make('quantity_grams')->grams(4),
                TextColumn::make('rate_per_gram')->inr(),
                TextColumn::make('total_amount')->inr(),
                TextColumn::make('gst_amount')->inr(),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->dateTime('d M Y H:i'),
            ])
            ->filters([
                SelectFilter::make('metal_type')->options(['gold' => 'Gold', 'silver' => 'Silver']),
                FilamentDateFilters::tableFilter('date', 'created_at', 'Date'),
            ])
            ->headerActions([static::reportExportAction(InvestmentReportExporter::class)])
            ->defaultSort('created_at', 'desc');
    }
}
