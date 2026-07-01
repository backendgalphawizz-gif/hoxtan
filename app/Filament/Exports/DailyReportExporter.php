<?php

namespace App\Filament\Exports;

use App\Models\DailyReport;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class DailyReportExporter extends Exporter
{
    protected static ?string $model = DailyReport::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('report_date')->label('Date'),
            ExportColumn::make('new_users')->label('New Users'),
            ExportColumn::make('active_investors')->label('Active Investors'),
            ExportColumn::make('gold_holdings_total')->label('Gold Holdings'),
            ExportColumn::make('silver_holdings_total')->label('Silver Holdings'),
            ExportColumn::make('revenue_total')->label('Revenue'),
            ExportColumn::make('transaction_count')->label('Transactions'),
            ExportColumn::make('gst_collected')->label('GST Collected'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Daily reports export completed. '.number_format($export->successful_rows).' rows exported.';
    }
}
