<?php

namespace App\Filament\Resources\KycDetailResource\Pages;

use App\Filament\Resources\KycDetailResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewKycDetail extends ViewRecord
{
    protected static string $resource = KycDetailResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\EditAction::make()];
    }
}
