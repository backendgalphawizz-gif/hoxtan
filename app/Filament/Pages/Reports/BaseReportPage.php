<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Filament\Pages\Reports\Concerns\InteractsWithReportExport;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;

abstract class BaseReportPage extends Page implements HasTable
{
    use InteractsWithAdminPermissions;
    use InteractsWithReportExport;
    use InteractsWithTable;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'admin.reports.table';

    public function getHubUrl(): string
    {
        return ReportsHub::getUrl();
    }
}
