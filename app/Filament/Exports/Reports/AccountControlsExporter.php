<?php

namespace App\Filament\Exports\Reports;

use App\Models\User;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class AccountControlsExporter extends Exporter
{
    protected static ?string $model = User::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('Client ID'),
            ExportColumn::make('name'),
            ExportColumn::make('phone'),
            ExportColumn::make('email'),
            ExportColumn::make('is_blocked')->label('Account Blocked'),
            ExportColumn::make('restriction.wallet_blocked')->label('Wallet Blocked'),
            ExportColumn::make('restriction.bonus_blocked')->label('Bonus Blocked'),
            ExportColumn::make('restriction.referral_blocked')->label('Referral Blocked'),
            ExportColumn::make('restriction.withdrawal_hold')->label('Withdrawal Hold'),
            ExportColumn::make('restriction.support_notes')->label('Support Notes'),
            ExportColumn::make('wallet_balance'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Account controls export completed. '.number_format($export->successful_rows).' rows exported.';
    }
}
