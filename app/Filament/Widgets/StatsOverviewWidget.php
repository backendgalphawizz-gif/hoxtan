<?php

namespace App\Filament\Widgets;

use App\Services\DashboardService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $stats = app(DashboardService::class)->getStats();

        return [
            Stat::make('Total Users', number_format($stats['total_users']))
                ->color('primary'),

            Stat::make('Today Users', number_format($stats['today_users']))
                ->color('primary'),

            Stat::make('Active Investors', number_format($stats['active_investors']))
                ->color('primary'),

            Stat::make('Total Gold Holdings', number_format($stats['total_gold_holdings'], 2).' g')
                ->color('primary'),

            Stat::make('Total Silver Holdings', number_format($stats['total_silver_holdings'], 2).' g')
                ->color('primary'),

            Stat::make('Today Gold Holdings', number_format($stats['today_gold_holdings'], 2).' g')
                ->color('primary'),

            Stat::make('Today Silver Holdings', number_format($stats['today_silver_holdings'], 2).' g')
                ->color('primary'),
        ];
    }
}
