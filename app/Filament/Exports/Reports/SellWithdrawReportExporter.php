<?php

namespace App\Filament\Exports\Reports;

use App\Models\Investment;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

class SellWithdrawReportExporter extends Exporter
{
    protected static ?string $model = Investment::class;

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->where('type', 'sell');
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('reference_id'),
            ExportColumn::make('user.id')->label('Client ID'),
            ExportColumn::make('user.name')->label('Name'),
            ExportColumn::make('metal_type'),
            ExportColumn::make('quantity_grams')->label('Quantity (g)'),
            ExportColumn::make('total_amount'),
            ExportColumn::make('status'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Sell & withdraw export completed. '.number_format($export->successful_rows).' rows exported.';
    }
}
