<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Exports\Reports\InactiveUsersReportExporter;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InactiveUsersReport extends BaseReportPage
{
    protected static function adminPermissionModule(): string
    {
        return 'reports_inactive_users';
    }

    protected static ?string $title = 'Inactive Users History';

    public function getSubheading(): ?string
    {
        return 'Users with no SIG activity in the last 90 days — includes past holdings snapshot.';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                User::query()
                    ->withCount('investments')
                    ->withMax('investments', 'created_at')
                    ->whereDoesntHave('investments', fn (Builder $q) => $q->where('created_at', '>=', now()->subDays(90)))
            )
            ->columns([
                TextColumn::make('id')->label('Client ID'),
                TextColumn::make('name')->searchable(),
                TextColumn::make('phone'),
                TextColumn::make('gold_holdings')->grams(4),
                TextColumn::make('silver_holdings')->grams(4),
                TextColumn::make('wallet_balance')->inr(),
                TextColumn::make('investments_count')->label('Total SIG Txns'),
                TextColumn::make('investments_max_created_at')->label('Last SIG')->dateTime('d M Y'),
                TextColumn::make('created_at')->label('Registered')->date('d M Y'),
            ])
            ->headerActions([
                static::reportExportAction(InactiveUsersReportExporter::class),
            ])
            ->defaultSort('investments_max_created_at', 'desc')
            ->paginated([25, 50, 100]);
    }
}
