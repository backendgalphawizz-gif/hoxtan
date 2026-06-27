<?php

namespace App\Filament\Widgets;

use App\Services\DashboardService;
use Filament\Widgets\ChartWidget;

class RevenueChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Revenue Analytics';

    protected static ?string $description = 'Revenue trend over the last 30 days';

    protected static ?int $sort = 2;

    protected static ?string $pollingInterval = null;

    protected static ?string $maxHeight = '280px';

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'xl' => 6,
    ];

    protected function getData(): array
    {
        $chartData = app(DashboardService::class)->getRevenueChartData(30);

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (₹)',
                    'data' => $chartData['values'],
                    'borderColor' => '#ea580c',
                    'backgroundColor' => 'rgba(234, 88, 12, 0.08)',
                    'pointBackgroundColor' => '#ea580c',
                    'pointBorderColor' => '#ffffff',
                    'pointRadius' => 4,
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
