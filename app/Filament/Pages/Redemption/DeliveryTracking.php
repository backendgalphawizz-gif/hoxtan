<?php

namespace App\Filament\Pages\Redemption;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Filament\Concerns\ListsRedemptions;
use App\Support\NavigationBadgeCounts;
use Filament\Pages\Page;
use Filament\Tables\Contracts\HasTable;

class DeliveryTracking extends Page implements HasTable
{
    use InteractsWithAdminPermissions;
    use ListsRedemptions;

    protected static function adminPermissionModule(): string
    {
        return 'delivery_tracking';
    }

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'Redemption Management';

    protected static ?string $navigationLabel = 'Delivery Tracking';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'admin.redemption.list';

    protected function redemptionStatuses(): ?array
    {
        return ['dispatched'];
    }

    public function getSubheading(): ?string
    {
        return 'Track dispatched redemptions until delivery is confirmed.';
    }

    public static function getNavigationBadge(): ?string
    {
        return NavigationBadgeCounts::format(NavigationBadgeCounts::dispatchedRedemptions());
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }
}
