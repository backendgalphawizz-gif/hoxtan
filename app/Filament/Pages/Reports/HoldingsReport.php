<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Exports\Reports\HoldingsReportExporter;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HoldingsReport extends BaseReportPage
{
    protected static function adminPermissionModule(): string
    {
        return 'reports_holdings';
    }

    protected static ?string $title = 'Total Holdings Report';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                User::query()->where(function ($query): void {
                    $query->where('gold_holdings', '>', 0)
                        ->orWhere('silver_holdings', '>', 0);
                })
            )
            ->columns([
                TextColumn::make('id')->label('Client ID'),
                TextColumn::make('name')->searchable(),
                TextColumn::make('phone'),
                TextColumn::make('gold_holdings')->grams(4)->summarize(\Filament\Tables\Columns\Summarizers\Sum::make()),
                TextColumn::make('silver_holdings')->grams(4)->summarize(\Filament\Tables\Columns\Summarizers\Sum::make()),
                TextColumn::make('wallet_balance')->inr()->summarize(\Filament\Tables\Columns\Summarizers\Sum::make()),
            ])
            ->headerActions([
                static::reportExportAction(HoldingsReportExporter::class),
            ])
            ->defaultSort('gold_holdings', 'desc');
    }
}
