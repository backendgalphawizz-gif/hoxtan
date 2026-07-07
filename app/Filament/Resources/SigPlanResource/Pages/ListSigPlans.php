<?php

namespace App\Filament\Resources\SigPlanResource\Pages;

use App\Filament\Resources\SigPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSigPlans extends ListRecords
{
    protected static string $resource = SigPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Activate SIG'),
        ];
    }
}
