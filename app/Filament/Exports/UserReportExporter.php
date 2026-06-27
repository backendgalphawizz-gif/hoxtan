<?php

namespace App\Filament\Exports;

use App\Models\User;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class UserReportExporter extends Exporter
{
    protected static ?string $model = User::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('name')->label('Name'),
            ExportColumn::make('email')->label('Email'),
            ExportColumn::make('phone')->label('Phone'),
            ExportColumn::make('role')->label('Role'),
            ExportColumn::make('kyc_status')->label('KYC Status'),
            ExportColumn::make('gold_holdings')->label('Gold (g)'),
            ExportColumn::make('silver_holdings')->label('Silver (g)'),
            ExportColumn::make('wallet_balance')->label('Wallet Balance'),
            ExportColumn::make('is_verified')->label('Verified'),
            ExportColumn::make('created_at')->label('Registered'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $count = number_format($export->successful_rows);

        return "User report export completed. {$count} rows exported.";
    }
}
