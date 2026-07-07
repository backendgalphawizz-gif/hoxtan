<?php

namespace App\Filament\Exports\Reports;

use App\Models\User;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class NewUsersReportExporter extends Exporter
{
    protected static ?string $model = User::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('Client ID'),
            ExportColumn::make('name'),
            ExportColumn::make('phone'),
            ExportColumn::make('email'),
            ExportColumn::make('kyc_status'),
            ExportColumn::make('wallet_balance'),
            ExportColumn::make('gold_holdings')->label('Gold (g)'),
            ExportColumn::make('silver_holdings')->label('Silver (g)'),
            ExportColumn::make('investments_count')->counts('investments')->label('SIG Txns'),
            ExportColumn::make('referrals_made_count')->counts('referralsMade')->label('Referrals'),
            ExportColumn::make('created_at')->label('Registered'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'New user report export completed. '.number_format($export->successful_rows).' rows exported.';
    }
}
