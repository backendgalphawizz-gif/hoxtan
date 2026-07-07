<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Exports\Reports\SellWithdrawReportExporter;
use App\Models\Investment;
use App\Models\Redemption;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SellWithdrawReport extends BaseReportPage
{
    protected static function adminPermissionModule(): string
    {
        return 'reports_sell_withdraw';
    }

    protected static ?string $title = 'Sell & Withdraw Report';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Investment::query()
                    ->with('user')
                    ->where('type', 'sell')
                    ->select('investments.*')
                    ->selectRaw("'sell' as record_type")
            )
            ->columns([
                TextColumn::make('reference_id')->label('Ref')->searchable(),
                TextColumn::make('user.name'),
                TextColumn::make('metal_type')->badge(),
                TextColumn::make('quantity_grams')->grams(4),
                TextColumn::make('total_amount')->inr(),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->dateTime('d M Y'),
            ])
            ->headerActions([static::reportExportAction(SellWithdrawReportExporter::class)])
            ->defaultSort('created_at', 'desc')
            ->heading('SIG Sell Transactions — see Redemption report for physical withdrawals');
    }
}
