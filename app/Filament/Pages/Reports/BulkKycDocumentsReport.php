<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Exports\KycDetailExporter;
use App\Models\KycDetail;

class BulkKycDocumentsReport extends ClientKycReport
{
    protected static function adminPermissionModule(): string
    {
        return 'reports_kyc_documents';
    }

    protected static ?string $title = 'Bulk KYC Documents';

    public function getSubheading(): ?string
    {
        return 'Export all KYC records including document file paths for compliance download.';
    }

    public function table(\Filament\Tables\Table $table): \Filament\Tables\Table
    {
        return parent::table($table)
            ->query(KycDetail::query()->with('user'))
            ->headerActions([
                static::reportExportAction(KycDetailExporter::class),
            ]);
    }
}
