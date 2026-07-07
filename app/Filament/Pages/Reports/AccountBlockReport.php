<?php

namespace App\Filament\Pages\Reports;

class AccountBlockReport extends AccountControlsReport
{
    protected static function adminPermissionModule(): string
    {
        return 'reports_account_block';
    }

    protected static ?string $title = 'Full Account Block';
}
