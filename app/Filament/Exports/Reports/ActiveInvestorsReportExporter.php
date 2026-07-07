<?php

namespace App\Filament\Exports\Reports;

use App\Models\User;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

class ActiveInvestorsReportExporter extends Exporter
{
    protected static ?string $model = User::class;

    public static function modifyQuery(Builder $query): Builder
    {
        return $query
            ->withCount(['investments as buy_count' => fn (Builder $q) => $q->where('type', 'buy')])
            ->withCount(['investments as sell_count' => fn (Builder $q) => $q->where('type', 'sell')])
            ->withCount('walletTransactions')
            ->where(function (Builder $inner): void {
                $inner->where('gold_holdings', '>', 0)
                    ->orWhere('silver_holdings', '>', 0)
                    ->orWhereHas('investments', fn (Builder $q) => $q->where('created_at', '>=', now()->subDays(90)));
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
            ExportColumn::make('buy_count')->label('Buys'),
            ExportColumn::make('sell_count')->label('Sells'),
            ExportColumn::make('wallet_transactions_count')->counts('walletTransactions')->label('Wallet Txns'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Active investors export completed. '.number_format($export->successful_rows).' rows exported.';
    }
}
