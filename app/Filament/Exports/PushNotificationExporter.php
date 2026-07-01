<?php

namespace App\Filament\Exports;

use App\Models\PushNotification;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class PushNotificationExporter extends Exporter
{
    protected static ?string $model = PushNotification::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('title')->label('Title'),
            ExportColumn::make('body')->label('Body'),
            ExportColumn::make('target')->label('Target'),
            ExportColumn::make('status')->label('Status'),
            ExportColumn::make('recipients_count')->label('Recipients'),
            ExportColumn::make('scheduled_at')->label('Scheduled'),
            ExportColumn::make('sent_at')->label('Sent'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Push notifications export completed. '.number_format($export->successful_rows).' rows exported.';
    }
}
