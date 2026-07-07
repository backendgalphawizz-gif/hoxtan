<?php

namespace App\Filament\Exports\Reports;

use App\Models\User;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

class InactiveUsersReportExporter extends Exporter
{
    protected static ?string $model = User::class;

    public static function modifyQuery(Builder $query): Builder
    {
        return $query
            ->withCount('investments')
            ->withMax('investments', 'created_at')
            ->whereDoesntHave('investments', fn (Builder $q) => $q->where('created_at', '>=', now()->subDays(90)));
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
            ExportColumn::make('investments_count')->counts('investments')->label('Total SIG Txns'),
            ExportColumn::make('investments_max_created_at')->label('Last SIG'),
            ExportColumn::make('created_at')->label('Registered'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Inactive users export completed. '.number_format($export->successful_rows).' rows exported.';
    }
}
