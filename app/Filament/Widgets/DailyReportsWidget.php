<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\DailyReportResource;
use App\Models\DailyReport;
use App\Services\DashboardService;
use App\Services\GstService;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class DailyReportsWidget extends BaseWidget
{
    protected static ?string $heading = 'Daily Reports';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $description = 'Generated daily platform summaries.';

    public function table(Table $table): Table
    {
        return DailyReportResource::table($table)
            ->query(DailyReport::query()->latest('report_date'))
            ->defaultPaginationPageOption(5)
            ->paginated([5, 10])
            ->headerActions([
                \Filament\Tables\Actions\Action::make('generate')
                    ->label('Generate Today\'s Report')
                    ->icon('heroicon-o-document-chart-bar')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->action(function (): void {
                        app(DashboardService::class)->generateDailyReport();
                        app(GstService::class)->calculateForDate(now());

                        \Filament\Notifications\Notification::make()
                            ->title('Daily report generated')
                            ->success()
                            ->send();
                    }),
                \Filament\Tables\Actions\Action::make('viewAll')
                    ->label('View All Reports')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(DailyReportResource::getUrl('index')),
            ]);
    }
}
