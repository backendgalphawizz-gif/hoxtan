<?php

namespace App\Filament\Exports\Reports;

use App\Models\JewelleryOrder;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class JewelleryActivityExporter extends Exporter
{
    protected static ?string $model = JewelleryOrder::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('order_number'),
            ExportColumn::make('user.id')->label('Client ID'),
            ExportColumn::make('user.name')->label('Name'),
            ExportColumn::make('status'),
            ExportColumn::make('payment.status')->label('Payment Status'),
            ExportColumn::make('subtotal'),
            ExportColumn::make('total_amount'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Jewellery activity export completed. '.number_format($export->successful_rows).' rows exported.';
    }
}
