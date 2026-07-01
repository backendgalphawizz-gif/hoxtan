<?php

namespace App\Filament\Exports;

use App\Models\MetalRate;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class MetalRateExporter extends Exporter
{
    protected static ?string $model = MetalRate::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('metal_type')->label('Metal'),
            ExportColumn::make('rate_per_gram')->label('Rate/Gram'),
            ExportColumn::make('source')->label('Source'),
            ExportColumn::make('is_active')->label('Active'),
            ExportColumn::make('updatedBy.name')->label('Updated By'),
            ExportColumn::make('notes')->label('Notes'),
            ExportColumn::make('created_at')->label('Created'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Metal rates export completed. '.number_format($export->successful_rows).' rows exported.';
    }
}
