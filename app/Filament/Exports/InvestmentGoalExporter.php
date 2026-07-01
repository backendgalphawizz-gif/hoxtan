<?php

namespace App\Filament\Exports;

use App\Models\InvestmentGoal;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class InvestmentGoalExporter extends Exporter
{
    protected static ?string $model = InvestmentGoal::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('user.name')->label('User'),
            ExportColumn::make('title')->label('Goal'),
            ExportColumn::make('metal_type')->label('Metal'),
            ExportColumn::make('target_grams')->label('Target (g)'),
            ExportColumn::make('current_grams')->label('Current (g)'),
            ExportColumn::make('target_amount')->label('Target Amount'),
            ExportColumn::make('target_date')->label('Target Date'),
            ExportColumn::make('status')->label('Status'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Investment goals export completed. '.number_format($export->successful_rows).' rows exported.';
    }
}
