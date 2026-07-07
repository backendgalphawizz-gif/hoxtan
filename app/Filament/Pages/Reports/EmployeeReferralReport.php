<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Exports\Reports\EmployeeReferralExporter;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmployeeReferralReport extends BaseReportPage
{
    protected static function adminPermissionModule(): string
    {
        return 'reports_employees';
    }

    protected static ?string $title = 'Employee & Referral IDs';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                User::query()
                    ->where('is_employee', true)
                    ->withCount('referralsMade')
                    ->with('referredBy:id,name,phone,employee_code')
            )
            ->columns([
                TextColumn::make('id')->label('Client ID'),
                TextColumn::make('employee_code'),
                TextColumn::make('name'),
                TextColumn::make('phone'),
                TextColumn::make('referral_code'),
                TextColumn::make('referrals_made_count')->label('Referrals'),
                TextColumn::make('referredBy.name')->label('Referred By'),
            ])
            ->headerActions([static::reportExportAction(EmployeeReferralExporter::class)])
            ->emptyStateHeading('No employee accounts yet')
            ->emptyStateDescription('Mark users as employees from User Management to track referral IDs.');
    }
}
