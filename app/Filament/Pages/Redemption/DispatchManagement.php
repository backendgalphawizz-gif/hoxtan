<?php

namespace App\Filament\Pages\Redemption;

use App\Filament\Concerns\ListsRedemptions;
use Filament\Pages\Page;
use Filament\Tables\Contracts\HasTable;

use App\Support\NavigationBadgeCounts;

class DispatchManagement extends Page implements HasTable
{
    use ListsRedemptions;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Redemption Management';

    protected static ?string $navigationLabel = 'Dispatch Management';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'admin.redemption.list';

    protected function redemptionStatuses(): ?array
    {
        return ['approved', 'processing'];
    }

    public function getSubheading(): ?string
    {
        return 'Assign courier details and dispatch approved redemptions.';
    }

    public static function getNavigationBadge(): ?string
    {
        return NavigationBadgeCounts::format(NavigationBadgeCounts::dispatchQueueRedemptions());
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }
}
