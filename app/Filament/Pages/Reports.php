<?php

namespace App\Filament\Pages;

use App\Filament\Exports\InvestmentReportExporter;
use App\Filament\Exports\RedemptionReportExporter;
use App\Filament\Exports\RevenueReportExporter;
use App\Filament\Exports\TaxReportExporter;
use App\Filament\Exports\UserReportExporter;
use App\Models\GstRecord;
use App\Models\Investment;
use App\Models\Redemption;
use App\Models\User;
use App\Support\FilamentDateFilters;
use Filament\Pages\Page;
use Filament\Tables\Actions\ExportAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class Reports extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Reports';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'admin.reports.index';

    public function getSubheading(): ?string
    {
        return 'Investment, revenue, user, tax, and redemption reports with export.';
    }

    public string $activeTab = 'investment';

    public function updatedActiveTab(): void
    {
        $this->resetTable();
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->updatedActiveTab();
    }

    public function table(Table $table): Table
    {
        return match ($this->activeTab) {
            'revenue' => $this->revenueTable($table),
            'users' => $this->userTable($table),
            'tax' => $this->taxTable($table),
            'redemption' => $this->redemptionTable($table),
            default => $this->investmentTable($table),
        };
    }

    protected function investmentTable(Table $table): Table
    {
        return $table
            ->query(Investment::query()->with('user'))
            ->columns([
                TextColumn::make('reference_id')->searchable()->copyable(),
                TextColumn::make('user.name')->searchable()->sortable(),
                TextColumn::make('metal_type')->badge(),
                TextColumn::make('type')->badge(),
                TextColumn::make('quantity_grams')->label('Qty (g)')->grams(4),
                TextColumn::make('total_amount')->inr()->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->dateTime('d M Y H:i')->sortable(),
            ])
            ->filters([
                FilamentDateFilters::tableFilter('transaction_date', 'created_at', 'Transaction Date'),
            ])
            ->headerActions([
                ExportAction::make()->exporter(InvestmentReportExporter::class),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    protected function revenueTable(Table $table): Table
    {
        return $table
            ->query(
                Investment::query()
                    ->with('user')
                    ->where('status', 'completed')
            )
            ->columns([
                TextColumn::make('reference_id')->searchable(),
                TextColumn::make('user.name')->searchable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('metal_type')->badge(),
                TextColumn::make('total_amount')->inr()->sortable(),
                TextColumn::make('gst_amount')->inr(),
                TextColumn::make('created_at')->dateTime('d M Y')->sortable(),
            ])
            ->filters([
                FilamentDateFilters::tableFilter('transaction_date', 'created_at', 'Transaction Date'),
            ])
            ->headerActions([
                ExportAction::make()->exporter(RevenueReportExporter::class),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    protected function userTable(Table $table): Table
    {
        return $table
            ->query(User::query())
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('role')->badge(),
                TextColumn::make('kyc_status')->badge(),
                TextColumn::make('gold_holdings')->label('Gold (g)')->grams(4),
                TextColumn::make('silver_holdings')->label('Silver (g)')->grams(4),
                TextColumn::make('wallet_balance')->inr(),
                TextColumn::make('created_at')->date('d M Y')->sortable(),
            ])
            ->filters([
                FilamentDateFilters::tableFilter('registration_date', 'created_at', 'Registration Date'),
            ])
            ->headerActions([
                ExportAction::make()->exporter(UserReportExporter::class),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    protected function taxTable(Table $table): Table
    {
        return $table
            ->query(GstRecord::query())
            ->columns([
                TextColumn::make('report_date')->label('Date')->date('d M Y')->sortable(),
                TextColumn::make('total_taxable_amount')->inr()->sortable(),
                TextColumn::make('cgst_amount')->inr(),
                TextColumn::make('sgst_amount')->inr(),
                TextColumn::make('total_gst')->inr()->weight('bold'),
                TextColumn::make('transaction_count')->badge(),
            ])
            ->filters([
                FilamentDateFilters::tableFilter('report_date', 'report_date', 'Report Date'),
            ])
            ->headerActions([
                ExportAction::make()->exporter(TaxReportExporter::class),
            ])
            ->defaultSort('report_date', 'desc')
            ->paginated([10, 25, 50]);
    }

    protected function redemptionTable(Table $table): Table
    {
        return $table
            ->query(Redemption::query()->with('user'))
            ->columns([
                TextColumn::make('reference_id')->searchable()->copyable(),
                TextColumn::make('user.name')->searchable(),
                TextColumn::make('metal_type')->badge(),
                TextColumn::make('quantity_grams')->label('Qty (g)')->grams(4),
                TextColumn::make('amount')->inr(),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->dateTime('d M Y')->sortable(),
            ])
            ->filters([
                FilamentDateFilters::tableFilter('request_date', 'created_at', 'Request Date'),
            ])
            ->headerActions([
                ExportAction::make()->exporter(RedemptionReportExporter::class),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }
}
