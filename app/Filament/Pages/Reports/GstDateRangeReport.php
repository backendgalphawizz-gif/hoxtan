<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Exports\TaxReportExporter;
use App\Models\GstRecord;
use App\Support\FilamentDateFilters;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GstDateRangeReport extends BaseReportPage
{
    protected static function adminPermissionModule(): string
    {
        return 'reports_gst_file';
    }

    protected static ?string $title = 'GST File (Date Range)';

    public function table(Table $table): Table
    {
        return $table
            ->query(GstRecord::query())
            ->columns([
                TextColumn::make('report_date')->date('d M Y')->sortable(),
                TextColumn::make('total_taxable_amount')->inr(),
                TextColumn::make('cgst_amount')->inr(),
                TextColumn::make('sgst_amount')->inr(),
                TextColumn::make('igst_amount')->inr(),
                TextColumn::make('total_gst')->inr()->weight('bold'),
                TextColumn::make('transaction_count')->badge(),
            ])
            ->filters([
                FilamentDateFilters::tableFilter('from_to', 'report_date', 'GST Date Range'),
            ])
            ->headerActions([static::reportExportAction(TaxReportExporter::class)])
            ->defaultSort('report_date', 'desc');
    }
}
