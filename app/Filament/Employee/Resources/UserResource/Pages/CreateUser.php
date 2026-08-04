<?php

namespace App\Filament\Employee\Resources\UserResource\Pages;

use App\Filament\Employee\Resources\UserResource;
use App\Models\Employee;
use App\Services\UserRegistrationService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['role'] = 'user';
        $data['kyc_status'] = $data['kyc_status'] ?? 'pending';
        $data['is_verified'] = true;
        $data['is_blocked'] = false;
        $data['is_employee'] = false;

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        /** @var Employee $creator */
        $creator = Auth::guard('employee')->user();

        $registration = app(UserRegistrationService::class);

        $user = $registration->register(
            $data['name'],
            $data['phone'],
            $data['mpin'],
            $this->data['referral_code_input'] ?? null,
        );

        $user->update([
            'role' => 'user',
            'kyc_status' => $data['kyc_status'] ?? 'pending',
            'is_verified' => true,
            'is_blocked' => false,
            'is_employee' => false,
            'created_by_employee_id' => $creator->id,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'nominee_name' => $data['nominee_name'] ?? null,
            'nominee_relation' => $data['nominee_relation'] ?? null,
            'nominee_phone' => $data['nominee_phone'] ?? null,
            'nominee_date_of_birth' => $data['nominee_date_of_birth'] ?? null,
        ]);

        return $user->fresh();
    }
}
