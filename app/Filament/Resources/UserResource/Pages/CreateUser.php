<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\Pages\BaseCreateRecord;
use App\Filament\Resources\UserResource;
use App\Services\UserRegistrationService;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends BaseCreateRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $registration = app(UserRegistrationService::class);

        $user = $registration->register(
            $data['name'],
            $data['phone'],
            $data['mpin'],
            $this->data['referral_code_input'] ?? null,
        );

        $user->update([
            'role' => $data['role'] ?? 'user',
            'kyc_status' => $data['kyc_status'] ?? 'pending',
            'is_verified' => $data['is_verified'] ?? true,
            'is_blocked' => $data['is_blocked'] ?? false,
            'nominee_name' => $data['nominee_name'] ?? null,
            'nominee_relation' => $data['nominee_relation'] ?? null,
            'nominee_phone' => $data['nominee_phone'] ?? null,
            'nominee_date_of_birth' => $data['nominee_date_of_birth'] ?? null,
        ]);

        return $user->fresh();
    }
}
