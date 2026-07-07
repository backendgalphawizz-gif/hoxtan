<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Exports\Reports\TransactionBreakdownExporter;
use App\Models\WalletTransaction;
use App\Support\FilamentDateFilters;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TransactionBreakdownReport extends BaseReportPage
{
    protected static function adminPermissionModule(): string
    {
        return 'reports_transactions';
    }

    protected static ?string $title = 'Transaction Breakdown';

    public function getSubheading(): ?string
    {
        return 'Debit, credit, bonus, referral and wallet movements with export.';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(WalletTransaction::query()->with('user'))
            ->columns([
                TextColumn::make('reference_id')->searchable()->copyable(),
                TextColumn::make('user.name')->searchable(),
                TextColumn::make('user.phone'),
                TextColumn::make('type')->badge()->color(fn (string $state) => $state === 'credit' ? 'success' : 'danger'),
                TextColumn::make('source')->badge(),
                TextColumn::make('amount')->inr(),
                TextColumn::make('balance_after')->inr(),
                TextColumn::make('description')->limit(40),
                TextColumn::make('created_at')->dateTime('d M Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->options(['credit' => 'Credit', 'debit' => 'Debit']),
                SelectFilter::make('source')->options([
                    'admin' => 'Admin',
                    'investment' => 'Investment',
                    'redemption' => 'Redemption',
                    'refund' => 'Refund',
                    'welcome_bonus' => 'Welcome Bonus',
                    'referral_bonus' => 'Referral Bonus',
                    'other' => 'Other',
                ]),
                FilamentDateFilters::tableFilter('transaction_date', 'created_at', 'Date'),
            ])
            ->headerActions([
                static::reportExportAction(TransactionBreakdownExporter::class),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100]);
    }
}
