<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Exports\InvestmentReportExporter;
use App\Models\Investment;
use App\Support\FilamentDateFilters;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class SigPeriodicReport extends BaseReportPage
{
    protected static function adminPermissionModule(): string
    {
        return 'reports_sig_periodic';
    }

    protected static ?string $title = 'SIG Periodic Reports';

    public ?string $period = 'daily';

    public function form(Form $form): Form
    {
        return $form->schema([
            Select::make('period')
                ->options([
                    'daily' => 'Daily',
                    'weekly' => 'Weekly',
                    'monthly' => 'Monthly',
                ])
                ->live()
                ->afterStateUpdated(fn () => $this->resetTable()),
        ]);
    }

    public function table(Table $table): Table
    {
        $since = match ($this->period) {
            'weekly' => now()->subWeeks(12),
            'monthly' => now()->subMonths(12),
            default => now()->subDays(30),
        };

        return $table
            ->query(
                Investment::query()
                    ->with('user')
                    ->where('created_at', '>=', $since)
            )
            ->columns([
                TextColumn::make('reference_id')->searchable(),
                TextColumn::make('user.name'),
                TextColumn::make('metal_type')->badge(),
                TextColumn::make('type')->badge(),
                TextColumn::make('quantity_grams')->grams(4),
                TextColumn::make('total_amount')->inr(),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->dateTime('d M Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->options(['buy' => 'Buy', 'sell' => 'Sell']),
                SelectFilter::make('metal_type')->options(['gold' => 'Gold', 'silver' => 'Silver']),
                FilamentDateFilters::tableFilter('period', 'created_at', 'Date'),
            ])
            ->headerActions([
                static::reportExportAction(InvestmentReportExporter::class),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
