<?php

namespace App\Filament\Widgets;

use App\Services\MetalRateService;
use App\Support\AdminPermissions;
use Filament\Widgets\Widget;

class CurrentMetalRatesWidget extends Widget
{
    protected static string $view = 'admin.widgets.current-metal-rates';

    protected static ?int $sort = 0;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return AdminPermissions::canViewModule('dashboard');
    }

    public function getRates(): array
    {
        return app(MetalRateService::class)->getDashboardRates();
    }

    public function formatSource(?string $source): string
    {
        if ($source === 'metals_api') {
            return 'Metals-API';
        }

        if (blank($source)) {
            return '—';
        }

        return ucwords(str_replace('_', ' ', $source));
    }
}
