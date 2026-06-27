<?php

namespace App\Filament\Exports;

use App\Models\Investment;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class RevenueReportExporter extends Exporter
{
    protected static ?string $model = Investment::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('reference_id')->label('Reference ID'),
            ExportColumn::make('user.name')->label('User'),
            ExportColumn::make('type')->label('Type'),
            ExportColumn::make('metal_type')->label('Metal'),
            ExportColumn::make('total_amount')->label('Revenue'),
            ExportColumn::make('gst_amount')->label('GST'),
            ExportColumn::make('created_at')->label('Date'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $count = number_format($export->successful_rows);

        return "Revenue report export completed. {$count} rows exported.";
    }
}
