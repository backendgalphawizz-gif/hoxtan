<?php

namespace App\Filament\Exports\Reports;

use App\Models\WalletTransaction;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class TransactionBreakdownExporter extends Exporter
{
    protected static ?string $model = WalletTransaction::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('reference_id'),
            ExportColumn::make('user.id')->label('Client ID'),
            ExportColumn::make('user.name')->label('Name'),
            ExportColumn::make('user.phone')->label('Phone'),
            ExportColumn::make('type'),
            ExportColumn::make('source'),
            ExportColumn::make('amount'),
            ExportColumn::make('balance_after'),
            ExportColumn::make('description'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Transaction breakdown export completed. '.number_format($export->successful_rows).' rows exported.';
    }
}
