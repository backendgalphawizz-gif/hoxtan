<?php

namespace App\Filament\Exports;

use App\Models\StaticPage;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class StaticPageExporter extends Exporter
{
    protected static ?string $model = StaticPage::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('title')->label('Title'),
            ExportColumn::make('slug')->label('Slug'),
            ExportColumn::make('is_published')->label('Published'),
            ExportColumn::make('updated_at')->label('Updated'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Static pages export completed. '.number_format($export->successful_rows).' rows exported.';
    }
}
