<?php

namespace App\Filament\Exports;

use App\Models\Faq;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class FaqExporter extends Exporter
{
    protected static ?string $model = Faq::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('question')->label('Question'),
            ExportColumn::make('answer')->label('Answer'),
            ExportColumn::make('sort_order')->label('Sort Order'),
            ExportColumn::make('is_active')->label('Active'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'FAQs export completed. '.number_format($export->successful_rows).' rows exported.';
    }
}
