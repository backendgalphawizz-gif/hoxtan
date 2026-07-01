<?php

namespace App\Filament\Exports;

use App\Models\WalletTransaction;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class WalletTransactionExporter extends Exporter
{
    protected static ?string $model = WalletTransaction::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('reference_id')->label('Reference'),
            ExportColumn::make('user.name')->label('User'),
            ExportColumn::make('type')->label('Type'),
            ExportColumn::make('amount')->label('Amount'),
            ExportColumn::make('balance_after')->label('Balance After'),
            ExportColumn::make('description')->label('Description'),
            ExportColumn::make('source')->label('Source'),
            ExportColumn::make('created_at')->label('Date'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Wallet transactions export completed. '.number_format($export->successful_rows).' rows exported.';
    }
}
