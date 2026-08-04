<?php

namespace App\Filament\Resources\ReferralResource\Pages;

use App\Filament\Resources\ReferralResource;
use Filament\Resources\Pages\ListRecords;

class ListReferrals extends ListRecords
{
    protected static string $resource = ReferralResource::class;

    public function getSubheading(): ?string
    {
        return 'Track referral sign-ups. Bonus is paid after the referred user crosses the purchase threshold.';
    }
}
