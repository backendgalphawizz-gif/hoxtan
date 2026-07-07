<?php

namespace App\Filament\Exports\Reports;

use App\Models\KycDetail;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class ClientKycReportExporter extends Exporter
{
    protected static ?string $model = KycDetail::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('user.id')->label('Client ID'),
            ExportColumn::make('user.name')->label('Name'),
            ExportColumn::make('user.phone')->label('Phone'),
            ExportColumn::make('full_name'),
            ExportColumn::make('pan_number'),
            ExportColumn::make('aadhaar_number'),
            ExportColumn::make('bank_name'),
            ExportColumn::make('account_holder_name'),
            ExportColumn::make('account_number'),
            ExportColumn::make('ifsc_code'),
            ExportColumn::make('upi_id'),
            ExportColumn::make('face_verification_status'),
            ExportColumn::make('bank_verification_status'),
            ExportColumn::make('pan_document'),
            ExportColumn::make('aadhaar_front'),
            ExportColumn::make('aadhaar_back'),
            ExportColumn::make('selfie_photo'),
            ExportColumn::make('submitted_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Client KYC bundle export completed. '.number_format($export->successful_rows).' rows exported.';
    }
}
