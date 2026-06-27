<?php

namespace App\Filament\Resources\KycDetailResource\Pages;

use App\Filament\Resources\KycDetailResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListKycDetails extends ListRecords
{
    protected static string $resource = KycDetailResource::class;

    public function getSubheading(): ?string
    {
        return 'View KYC details, face verification review, and approve or reject submissions.';
    }

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
