<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Exports\Reports\AllPurchasesReportExporter;
use App\Models\Investment;
use App\Support\FilamentDateFilters;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AllPurchasesReport extends BaseReportPage
{
    protected static function adminPermissionModule(): string
    {
        return 'reports_all_purchases';
    }

    protected static ?string $title = 'All Purchases (Gold / Silver)';

    public function getSubheading(): ?string
    {
        return 'SIG buy transactions and invoices — jewellery purchases in Jewellery Activity report.';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Investment::query()->with('user')->where('type', 'buy'))
            ->columns([
                TextColumn::make('reference_id'),
                TextColumn::make('user.name'),
                TextColumn::make('metal_type')->badge(),
                TextColumn::make('quantity_grams')->grams(4),
                TextColumn::make('total_amount')->inr(),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->dateTime('d M Y'),
            ])
            ->filters([
                FilamentDateFilters::tableFilter('date', 'created_at', 'Purchase Date'),
            ])
            ->headerActions([static::reportExportAction(AllPurchasesReportExporter::class)])
            ->defaultSort('created_at', 'desc');
    }
}
