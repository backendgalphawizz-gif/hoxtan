<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Exports\Reports\GoalsOffersReportExporter;
use App\Models\InvestmentGoal;
use App\Models\Offer;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GoalsOffersReport extends BaseReportPage
{
    protected static function adminPermissionModule(): string
    {
        return 'reports_offers_goals';
    }

    protected static ?string $title = 'Goals & Targeted Offers';

    public string $viewType = 'goals';

    public function mount(): void
    {
        $tab = request()->query('tab');

        if (in_array($tab, ['goals', 'offers'], true)) {
            $this->viewType = $tab;
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('goalsTab')
                ->label('Investment Goals')
                ->color($this->viewType === 'goals' ? 'primary' : 'gray')
                ->url(static::getUrl(['tab' => 'goals'])),
            Action::make('offersTab')
                ->label('Targeted Offers')
                ->color($this->viewType === 'offers' ? 'primary' : 'gray')
                ->url(static::getUrl(['tab' => 'offers'])),
        ];
    }

    public function table(Table $table): Table
    {
        if ($this->viewType === 'offers') {
            return $table
                ->query(Offer::query())
                ->columns([
                    TextColumn::make('title'),
                    TextColumn::make('promo_code'),
                    TextColumn::make('for_all_users')->boolean()->label('All Users'),
                    TextColumn::make('target_user_ids')->label('Target IDs')->formatStateUsing(
                        fn ($state) => is_array($state) ? implode(', ', $state) : '—'
                    ),
                    TextColumn::make('is_active')->boolean(),
                ])
                ->headerActions([static::reportExportAction(GoalsOffersReportExporter::class)]);
        }

        return $table
            ->query(InvestmentGoal::query()->with('user'))
            ->columns([
                TextColumn::make('user.name'),
                TextColumn::make('title'),
                TextColumn::make('metal_type')->badge(),
                TextColumn::make('target_grams')->grams(4),
                TextColumn::make('current_grams')->grams(4),
                TextColumn::make('admin_created')->boolean(),
                TextColumn::make('status')->badge(),
            ])
            ->headerActions([static::reportExportAction(GoalsOffersReportExporter::class)]);
    }
}
