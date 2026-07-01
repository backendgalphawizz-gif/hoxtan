<?php

namespace App\Filament\Exports;

use App\Models\Redemption;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class RedemptionExporter extends Exporter
{
    protected static ?string $model = Redemption::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('reference_id')->label('Reference'),
            ExportColumn::make('user.name')->label('User'),
            ExportColumn::make('metal_type')->label('Metal'),
            ExportColumn::make('quantity_grams')->label('Quantity (g)'),
            ExportColumn::make('amount')->label('Amount'),
            ExportColumn::make('status')->label('Status'),
            ExportColumn::make('tracking_number')->label('Tracking'),
            ExportColumn::make('courier_name')->label('Courier'),
            ExportColumn::make('created_at')->label('Requested'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Redemptions export completed. '.number_format($export->successful_rows).' rows exported.';
    }
}
