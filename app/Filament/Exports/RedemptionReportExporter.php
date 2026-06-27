<?php

namespace App\Filament\Exports;

use App\Models\Redemption;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class RedemptionReportExporter extends Exporter
{
    protected static ?string $model = Redemption::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('reference_id')->label('Reference ID'),
            ExportColumn::make('user.name')->label('User'),
            ExportColumn::make('metal_type')->label('Metal'),
            ExportColumn::make('quantity_grams')->label('Quantity (g)'),
            ExportColumn::make('amount')->label('Amount'),
            ExportColumn::make('status')->label('Status'),
            ExportColumn::make('courier_name')->label('Courier'),
            ExportColumn::make('tracking_number')->label('Tracking'),
            ExportColumn::make('dispatched_at')->label('Dispatched'),
            ExportColumn::make('delivered_at')->label('Delivered'),
            ExportColumn::make('created_at')->label('Requested'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $count = number_format($export->successful_rows);

        return "Redemption report export completed. {$count} rows exported.";
    }
}
