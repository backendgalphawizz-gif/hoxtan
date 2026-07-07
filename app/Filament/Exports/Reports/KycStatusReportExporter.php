<?php

namespace App\Filament\Exports\Reports;

use App\Models\User;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class KycStatusReportExporter extends Exporter
{
    protected static ?string $model = User::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('Client ID'),
            ExportColumn::make('name'),
            ExportColumn::make('phone'),
            ExportColumn::make('kyc_status'),
            ExportColumn::make('kycDetail.pan_number')->label('PAN'),
            ExportColumn::make('kycDetail.aadhaar_number')->label('Aadhaar'),
            ExportColumn::make('kycDetail.face_verification_status')->label('Face'),
            ExportColumn::make('kycDetail.bank_verification_status')->label('Bank'),
            ExportColumn::make('kycDetail.rejection_reason')->label('Rejection Reason'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'KYC status export completed. '.number_format($export->successful_rows).' rows exported.';
    }
}
