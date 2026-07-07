<?php

namespace App\Filament\Exports\Reports;

use App\Models\User;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

class HoldingsReportExporter extends Exporter
{
    protected static ?string $model = User::class;

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->where(function (Builder $inner): void {
            $inner->where('gold_holdings', '>', 0)
                ->orWhere('silver_holdings', '>', 0);
        });
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('Client ID'),
            ExportColumn::make('name'),
            ExportColumn::make('phone'),
            ExportColumn::make('gold_holdings')->label('Gold (g)'),
            ExportColumn::make('silver_holdings')->label('Silver (g)'),
            ExportColumn::make('wallet_balance'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Holdings export completed. '.number_format($export->successful_rows).' rows exported.';
    }
}
