<?php

namespace App\Filament\Exports;

use App\Models\Investment;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class InvestmentReportExporter extends Exporter
{
    protected static ?string $model = Investment::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('reference_id')->label('Reference ID'),
            ExportColumn::make('user.name')->label('User'),
            ExportColumn::make('metal_type')->label('Metal'),
            ExportColumn::make('type')->label('Type'),
            ExportColumn::make('quantity_grams')->label('Quantity (g)'),
            ExportColumn::make('rate_per_gram')->label('Rate/Gram'),
            ExportColumn::make('amount')->label('Base Amount'),
            ExportColumn::make('gst_amount')->label('GST'),
            ExportColumn::make('total_amount')->label('Total'),
            ExportColumn::make('status')->label('Status'),
            ExportColumn::make('created_at')->label('Date'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $count = number_format($export->successful_rows);

        return "Investment report export completed. {$count} rows exported.";
    }
}
