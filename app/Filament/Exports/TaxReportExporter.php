<?php

namespace App\Filament\Exports;

use App\Models\GstRecord;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class TaxReportExporter extends Exporter
{
    protected static ?string $model = GstRecord::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('report_date')->label('Date'),
            ExportColumn::make('total_taxable_amount')->label('Taxable Amount'),
            ExportColumn::make('cgst_amount')->label('CGST'),
            ExportColumn::make('sgst_amount')->label('SGST'),
            ExportColumn::make('igst_amount')->label('IGST'),
            ExportColumn::make('total_gst')->label('Total GST'),
            ExportColumn::make('transaction_count')->label('Transactions'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $count = number_format($export->successful_rows);

        return "Tax report export completed. {$count} rows exported.";
    }
}
