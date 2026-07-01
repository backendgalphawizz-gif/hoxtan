<?php

namespace App\Filament\Exports;

use App\Models\Banner;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class BannerExporter extends Exporter
{
    protected static ?string $model = Banner::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('title')->label('Title'),
            ExportColumn::make('link')->label('Link'),
            ExportColumn::make('sort_order')->label('Sort Order'),
            ExportColumn::make('is_active')->label('Active'),
            ExportColumn::make('starts_at')->label('Starts'),
            ExportColumn::make('ends_at')->label('Ends'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Banners export completed. '.number_format($export->successful_rows).' rows exported.';
    }
}
