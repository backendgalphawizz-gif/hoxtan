<?php

namespace App\Filament\Exports;

use App\Models\KycDetail;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class KycDetailExporter extends Exporter
{
    protected static ?string $model = KycDetail::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('user.name')->label('User'),
            ExportColumn::make('full_name')->label('Full Name'),
            ExportColumn::make('pan_number')->label('PAN'),
            ExportColumn::make('aadhaar_number')->label('Aadhaar'),
            ExportColumn::make('city')->label('City'),
            ExportColumn::make('state')->label('State'),
            ExportColumn::make('face_verification_status')->label('Face Verification'),
            ExportColumn::make('submitted_at')->label('Submitted'),
            ExportColumn::make('reviewed_at')->label('Reviewed'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'KYC export completed. '.number_format($export->successful_rows).' rows exported.';
    }
}
