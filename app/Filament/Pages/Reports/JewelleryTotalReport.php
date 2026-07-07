<?php

namespace App\Filament\Pages\Reports;

class JewelleryTotalReport extends JewelleryActivityReport
{
    protected static function adminPermissionModule(): string
    {
        return 'reports_jewellery_total';
    }

    protected static ?string $title = 'Total Jewellery Reports';
}
