<?php

namespace App\Filament\Exports;

use App\Models\Offer;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class OfferExporter extends Exporter
{
    protected static ?string $model = Offer::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('title')->label('Title'),
            ExportColumn::make('promo_code')->label('Promo Code'),
            ExportColumn::make('discount_type')->label('Discount Type'),
            ExportColumn::make('discount_value')->label('Discount Value'),
            ExportColumn::make('is_active')->label('Active'),
            ExportColumn::make('starts_at')->label('Starts'),
            ExportColumn::make('ends_at')->label('Ends'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Offers export completed. '.number_format($export->successful_rows).' rows exported.';
    }
}
