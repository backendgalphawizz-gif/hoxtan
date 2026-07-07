<?php

namespace App\Filament\Resources\SigPlanResource\Pages;

use App\Filament\Resources\SigPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSigPlan extends EditRecord
{
    protected static string $resource = SigPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
