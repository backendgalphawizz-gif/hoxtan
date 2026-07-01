<?php

namespace App\Filament\Widgets;

use App\Services\DashboardService;
use App\Support\AdminPermissions;
use Filament\Widgets\ChartWidget;

class TransactionChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Transaction Analytics';

    protected static ?string $description = 'Buy and sell transactions over the last 30 days';

    protected static ?int $sort = 3;

    public static function canView(): bool
    {
        return AdminPermissions::canViewModule('dashboard');
    }

    protected static ?string $pollingInterval = null;

    protected static ?string $maxHeight = '280px';

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'xl' => 6,
    ];

    protected function getData(): array
    {
        $chartData = app(DashboardService::class)->getTransactionChartData(30);

        return [
            'datasets' => [
                [
                    'label' => 'Buy',
                    'data' => $chartData['buy'],
                    'borderColor' => '#ea580c',
                    'backgroundColor' => 'rgba(234, 88, 12, 0.08)',
                    'pointBackgroundColor' => '#ea580c',
                    'fill' => true,
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Sell',
                    'data' => $chartData['sell'],
                    'borderColor' => '#9ca3af',
                    'backgroundColor' => 'rgba(156, 163, 175, 0.08)',
                    'pointBackgroundColor' => '#9ca3af',
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
            'labels' => $chartData['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
