<?php

namespace App\Filament\Exports\Reports;

use App\Models\User;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

class EmployeeReferralExporter extends Exporter
{
    protected static ?string $model = User::class;

    public static function modifyQuery(Builder $query): Builder
    {
        return $query
            ->where('is_employee', true)
            ->withCount('referralsMade')
            ->with('referredBy:id,name,phone,employee_code');
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('Client ID'),
            ExportColumn::make('employee_code'),
            ExportColumn::make('name'),
            ExportColumn::make('phone'),
            ExportColumn::make('referral_code'),
            ExportColumn::make('referrals_made_count')->counts('referralsMade')->label('Referrals'),
            ExportColumn::make('referredBy.name')->label('Referred By'),
            ExportColumn::make('referredBy.employee_code')->label('Referrer Code'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Employee referral export completed. '.number_format($export->successful_rows).' rows exported.';
    }
}
