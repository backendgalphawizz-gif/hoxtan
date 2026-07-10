<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Support\ApiResponse;
use App\Support\DriverPayload;
use App\Support\ProfilePhotoStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DriverProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var Driver $driver */
        $driver = $request->user();

        return ApiResponse::success([
            'driver' => DriverPayload::make($driver),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        /** @var Driver $driver */
        $driver = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100', 'regex:/^[A-Za-z\s]+$/'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('drivers', 'email')->ignore($driver->id),
            ],
            'primary_residence' => ['nullable', 'string', 'max:255'],
            'profile_photo' => ['nullable'],
            'image' => ['nullable'],
            'profile_image' => ['nullable'],
            'avatar' => ['nullable'],
            'photo' => ['nullable'],
            'profile_photo_base64' => ['nullable', 'string'],
            'profile_image_base64' => ['nullable', 'string'],
            'image_base64' => ['nullable', 'string'],
        ], [
            'name.regex' => 'Full name may only contain letters and spaces.',
        ]);

        $updates = collect($data)->only([
            'name',
            'email',
            'primary_residence',
        ])->all();

        if ($photoPath = ProfilePhotoStorage::storeForDriver($driver, $request)) {
            $updates['profile_image'] = $photoPath;
        }

        if ($updates === []) {
            throw ValidationException::withMessages([
                'message' => ['No profile fields were provided to update.'],
            ]);
        }

        $driver->update($updates);

        return ApiResponse::success([
            'driver' => DriverPayload::make($driver->fresh()),
        ], 'Profile updated successfully.');
    }

    public function updateAvailability(Request $request): JsonResponse
    {
        /** @var Driver $driver */
        $driver = $request->user();

        $data = $request->validate([
            'is_online' => ['required', 'boolean'],
        ]);

        $driver->update([
            'is_online' => (bool) $data['is_online'],
        ]);

        $message = $driver->is_online
            ? 'You are now online.'
            : 'You are now offline.';

        return ApiResponse::success([
            'driver' => DriverPayload::make($driver->fresh()),
        ], $message);
    }
}
