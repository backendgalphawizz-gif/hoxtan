<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Exports\Reports\NewUsersReportExporter;
use App\Models\User;
use App\Support\FilamentDateFilters;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NewUsersReport extends BaseReportPage
{
    protected static function adminPermissionModule(): string
    {
        return 'reports_new_users';
    }

    protected static ?string $title = 'New User Reports';

    public function getSubheading(): ?string
    {
        return 'Recently registered users with wallet, holdings, KYC and referral summary.';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                User::query()
                    ->withCount(['investments', 'walletTransactions', 'referralsMade'])
                    ->with('kycDetail')
            )
            ->columns([
                TextColumn::make('id')->label('Client ID')->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('phone')->searchable(),
                TextColumn::make('email')->searchable()->toggleable(),
                TextColumn::make('kyc_status')->badge(),
                TextColumn::make('wallet_balance')->inr(),
                TextColumn::make('gold_holdings')->label('Gold (g)')->grams(4),
                TextColumn::make('silver_holdings')->label('Silver (g)')->grams(4),
                TextColumn::make('investments_count')->label('SIG Txns')->badge(),
                TextColumn::make('referrals_made_count')->label('Referrals'),
                TextColumn::make('created_at')->dateTime('d M Y')->sortable(),
            ])
            ->filters([
                FilamentDateFilters::tableFilter('registered', 'created_at', 'Registration Date'),
            ])
            ->headerActions([
                static::reportExportAction(NewUsersReportExporter::class),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100]);
    }
}
