<?php

namespace App\Filament\Resources\SigPlanResource\Pages;

use App\Filament\Resources\Pages\BaseCreateRecord;
use App\Filament\Resources\SigPlanResource;
use App\Services\SigPlanService;
use Illuminate\Support\Facades\Auth;

class CreateSigPlan extends BaseCreateRecord
{
    protected static string $resource = SigPlanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['activated_at'] = now();
        $data['created_by'] = Auth::guard('admin')->id();
        $data['next_debit_at'] = app(SigPlanService::class)->nextDebitAt($data['frequency'])->toDateTimeString();

        return $data;
    }
}
