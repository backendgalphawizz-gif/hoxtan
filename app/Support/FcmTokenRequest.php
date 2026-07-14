<?php

namespace App\Support;

use Illuminate\Http\Request;

class FcmTokenRequest
{
    /**
     * Pull FCM token from common client field names.
     */
    public static function from(Request $request): ?string
    {
        $raw = $request->input('fcm_token')
            ?? $request->input('fcmToken')
            ?? $request->input('device_token')
            ?? $request->input('deviceToken')
            ?? $request->input('firebase_token');

        if (! is_string($raw)) {
            return null;
        }

        $token = trim($raw);

        return $token !== '' ? $token : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function validationRules(bool $required = false): array
    {
        $presence = $required ? ['required'] : ['nullable'];

        return [
            'fcm_token' => [...$presence, 'string', 'max:4096'],
            'fcmToken' => ['nullable', 'string', 'max:4096'],
            'device_token' => ['nullable', 'string', 'max:4096'],
            'platform' => ['nullable', 'string', 'in:android,ios,web'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ];
    }
}
