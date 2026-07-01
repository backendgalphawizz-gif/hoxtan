<?php

namespace App\Filament\Resources\KycDetailResource\Pages;

use App\Filament\Exports\KycDetailExporter;
use App\Filament\Resources\KycDetailResource;
use App\Support\FilamentExportActions;
use Filament\Resources\Pages\ListRecords;

class ListKycDetails extends ListRecords
{
    protected static string $resource = KycDetailResource::class;

    public function getSubheading(): ?string
    {
        return 'Review and verify user KYC submissions.';
    }

    protected function getHeaderActions(): array
    {
        return [
            FilamentExportActions::headerExport(KycDetailExporter::class, 'kyc'),
        ];
    }
}
