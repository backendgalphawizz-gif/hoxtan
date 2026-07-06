<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    use InteractsWithAdminPermissions;

    protected static function adminPermissionModule(): string
    {
        return 'dashboard';
    }

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationGroup = 'Dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = 0;

    public function getSubheading(): ?string
    {
        return 'Platform overview · Updated '.now()->format('M d, Y g:i A');
    }

    public function getColumns(): int|string|array
    {
        return [
            'default' => 1,
            'xl' => 12,
        ];
    }

    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\CurrentMetalRatesWidget::class,
            \App\Filament\Widgets\StatsOverviewWidget::class,
            \App\Filament\Widgets\RevenueChartWidget::class,
            \App\Filament\Widgets\TransactionChartWidget::class,
            \App\Filament\Widgets\DailyReportsWidget::class,
        ];
    }
}
