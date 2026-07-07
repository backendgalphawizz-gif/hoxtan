<?php

namespace App\Filament\Pages\Reports;

class WalletRestrictionsReport extends AccountControlsReport
{
    protected static function adminPermissionModule(): string
    {
        return 'reports_wallet_restrictions';
    }

    protected static ?string $title = 'Wallet / Bonus / Referral Restrictions';
}
