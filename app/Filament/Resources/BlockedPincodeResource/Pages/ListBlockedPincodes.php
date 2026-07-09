<?php

namespace App\Filament\Resources\BlockedPincodeResource\Pages;

use App\Filament\Pages\Delivery\BulkPincodeUpload;
use App\Filament\Resources\BlockedPincodeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBlockedPincodes extends ListRecords
{
    protected static string $resource = BlockedPincodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('bulk_upload')
                ->label('Bulk Upload')
                ->icon('heroicon-o-arrow-up-tray')
                ->url(BulkPincodeUpload::getUrl()),
            Actions\CreateAction::make(),
        ];
    }
}
