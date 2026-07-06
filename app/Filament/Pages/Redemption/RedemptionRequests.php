<?php

namespace App\Filament\Pages\Redemption;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Filament\Concerns\ListsRedemptions;
use App\Support\NavigationBadgeCounts;
use Filament\Pages\Page;
use Filament\Tables\Contracts\HasTable;

class RedemptionRequests extends Page implements HasTable
{
    use InteractsWithAdminPermissions;
    use ListsRedemptions;

    protected static function adminPermissionModule(): string
    {
        return 'redemption_requests';
    }

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationGroup = 'Redemption Management';

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('admin_navigation.redemption_management', true);
    }

    protected static ?string $navigationLabel = 'Redemption Requests';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'admin.redemption.list';

    protected function redemptionStatuses(): ?array
    {
        return ['pending'];
    }

    public function getSubheading(): ?string
    {
        return 'Review and approve or reject new redemption requests.';
    }

    public static function getNavigationBadge(): ?string
    {
        return NavigationBadgeCounts::format(NavigationBadgeCounts::pendingRedemptions());
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }
}
