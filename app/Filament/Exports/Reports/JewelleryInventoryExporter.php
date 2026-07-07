<?php

namespace App\Filament\Exports\Reports;

use App\Models\JewelleryProduct;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class JewelleryInventoryExporter extends Exporter
{
    protected static ?string $model = JewelleryProduct::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('sku'),
            ExportColumn::make('name'),
            ExportColumn::make('stock_status'),
            ExportColumn::make('price'),
            ExportColumn::make('weight_grams'),
            ExportColumn::make('metal_type'),
            ExportColumn::make('is_active'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Jewellery inventory export completed. '.number_format($export->successful_rows).' rows exported.';
    }
}
