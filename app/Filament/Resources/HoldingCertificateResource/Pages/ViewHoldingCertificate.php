<?php

namespace App\Filament\Resources\HoldingCertificateResource\Pages;

use App\Filament\Resources\HoldingCertificateResource;
use App\Models\HoldingCertificate;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewHoldingCertificate extends ViewRecord
{
    protected static string $resource = HoldingCertificateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download')
                ->label('Download Certificate')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->url(fn (HoldingCertificate $record): string => route('admin.certificates.download', $record))
                ->openUrlInNewTab(),
        ];
    }
}
