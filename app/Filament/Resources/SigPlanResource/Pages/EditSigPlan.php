<?php

namespace App\Filament\Resources\SigPlanResource\Pages;

use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Resources\SigPlanResource;
use Filament\Actions;

class EditSigPlan extends BaseEditRecord
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
