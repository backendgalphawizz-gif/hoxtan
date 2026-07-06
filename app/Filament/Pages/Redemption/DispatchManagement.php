<?php

namespace App\Filament\Pages\Redemption;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Filament\Concerns\ListsRedemptions;
use App\Support\NavigationBadgeCounts;
use Filament\Pages\Page;
use Filament\Tables\Contracts\HasTable;

class DispatchManagement extends Page implements HasTable
{
    use InteractsWithAdminPermissions;
    use ListsRedemptions;

    protected static function adminPermissionModule(): string
    {
        return 'dispatch_management';
    }

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Redemption Management';

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('admin_navigation.redemption_management', true);
    }

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
