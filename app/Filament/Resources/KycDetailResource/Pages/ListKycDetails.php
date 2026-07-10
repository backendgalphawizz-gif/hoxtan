<?php

namespace App\Filament\Resources\KycDetailResource\Pages;

use App\Filament\Resources\KycDetailResource;
use Filament\Resources\Pages\ListRecords;

class ListKycDetails extends ListRecords
{
    protected static string $resource = KycDetailResource::class;

    public function getSubheading(): ?string
    {
        return 'Review and verify user KYC submissions. Select rows with the checkboxes, then use Export selected from the selection bar.';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
