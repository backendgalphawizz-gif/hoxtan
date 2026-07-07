<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Exports\Reports\ActiveInvestorsReportExporter;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActiveInvestorsReport extends BaseReportPage
{
    protected static function adminPermissionModule(): string
    {
        return 'reports_active_investors';
    }

    protected static ?string $title = 'Active Investors';

    public function getSubheading(): ?string
    {
        return 'Users with holdings or completed SIG activity.';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                User::query()
                    ->withCount(['investments as buy_count' => fn (Builder $q) => $q->where('type', 'buy')])
                    ->withCount(['investments as sell_count' => fn (Builder $q) => $q->where('type', 'sell')])
                    ->withCount('walletTransactions')
                    ->withCount(['referralsMade as referral_credits' => fn (Builder $q) => $q->where('status', 'credited')])
                    ->where(function (Builder $query): void {
                        $query->where('gold_holdings', '>', 0)
                            ->orWhere('silver_holdings', '>', 0)
                            ->orWhereHas('investments', fn (Builder $q) => $q->where('created_at', '>=', now()->subDays(90)));
                    })
            )
            ->columns([
                TextColumn::make('id')->label('Client ID'),
                TextColumn::make('name')->searchable(),
                TextColumn::make('phone'),
                TextColumn::make('gold_holdings')->grams(4),
                TextColumn::make('silver_holdings')->grams(4),
                TextColumn::make('wallet_balance')->inr(),
                TextColumn::make('buy_count')->label('Buys'),
                TextColumn::make('sell_count')->label('Sells'),
                TextColumn::make('wallet_transactions_count')->label('Wallet Txns'),
                TextColumn::make('referral_credits')->label('Referral Bonus'),
            ])
            ->headerActions([
                static::reportExportAction(ActiveInvestorsReportExporter::class),
            ])
            ->defaultSort('gold_holdings', 'desc')
            ->paginated([25, 50, 100]);
    }
}
