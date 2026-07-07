<?php

namespace App\Filament\Resources\SigPlanResource\Pages;

use App\Filament\Resources\SigPlanResource;
use App\Services\SigPlanService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateSigPlan extends CreateRecord
{
    protected static string $resource = SigPlanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['activated_at'] = now();
        $data['created_by'] = Auth::guard('admin')->id();
        $data['next_debit_at'] = app(SigPlanService::class)->nextDebitAt($data['frequency'])->toDateTimeString();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
