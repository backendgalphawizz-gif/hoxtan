<?php

namespace App\Filament\Exports\Reports;

use App\Models\InvestmentGoal;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class GoalsOffersReportExporter extends Exporter
{
    protected static ?string $model = InvestmentGoal::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('user.id')->label('Client ID'),
            ExportColumn::make('user.name')->label('Name'),
            ExportColumn::make('title'),
            ExportColumn::make('metal_type'),
            ExportColumn::make('target_grams'),
            ExportColumn::make('current_grams'),
            ExportColumn::make('admin_created'),
            ExportColumn::make('status'),
            ExportColumn::make('target_date'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Goals & offers export completed. '.number_format($export->successful_rows).' rows exported.';
    }
}
