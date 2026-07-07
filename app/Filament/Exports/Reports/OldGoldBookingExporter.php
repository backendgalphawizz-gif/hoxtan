<?php

namespace App\Filament\Exports\Reports;

use App\Models\OldGoldBooking;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class OldGoldBookingExporter extends Exporter
{
    protected static ?string $model = OldGoldBooking::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('booking_number'),
            ExportColumn::make('user.id')->label('Client ID'),
            ExportColumn::make('user.name')->label('Name'),
            ExportColumn::make('status'),
            ExportColumn::make('estimated_weight_grams'),
            ExportColumn::make('quoted_amount'),
            ExportColumn::make('final_amount'),
            ExportColumn::make('payment.status')->label('Payment Status'),
            ExportColumn::make('completed_at'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Old gold booking export completed. '.number_format($export->successful_rows).' rows exported.';
    }
}
