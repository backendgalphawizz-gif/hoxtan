<?php

namespace App\Support;

use Illuminate\Http\Request;

class FcmTokenRequest
{
    /**
     * Pull FCM token from common client field names / headers.
     */
    public static function from(Request $request): ?string
    {
        $candidates = [
            $request->input('fcm_token'),
            $request->input('fcmToken'),
            $request->input('device_token'),
            $request->input('deviceToken'),
            $request->input('firebase_token'),
            $request->input('token'), // same key used by /notifications/device
            $request->input('data.fcm_token'),
            $request->input('data.fcmToken'),
            $request->input('data.token'),
            $request->header('X-FCM-Token'),
            $request->header('fcm_token'),
        ];

        foreach ($candidates as $raw) {
            if (! is_string($raw) && ! is_numeric($raw)) {
                continue;
            }

            $token = trim((string) $raw);

            // Ignore empty / placeholder values clients often send.
            if ($token === '' || strcasecmp($token, 'null') === 0 || strcasecmp($token, 'undefined') === 0) {
                continue;
            }

            return $token;
        }

        return null;
    }

    public static function platform(Request $request): ?string
    {
        $raw = $request->input('platform')
            ?? $request->input('data.platform')
            ?? null;

        if (! is_string($raw)) {
            return null;
        }

        $platform = strtolower(trim($raw));

        return in_array($platform, ['android', 'ios', 'web'], true) ? $platform : null;
    }

    public static function deviceName(Request $request): ?string
    {
        $raw = $request->input('device_name')
            ?? $request->input('deviceName')
            ?? $request->input('data.device_name')
            ?? null;

        if (! is_string($raw)) {
            return null;
        }

        $name = trim($raw);

        return $name !== '' ? mb_substr($name, 0, 120) : null;
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
            'token' => ['nullable', 'string', 'max:4096'],
            'platform' => ['nullable', 'string', 'in:android,ios,web'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'data' => ['nullable', 'array'],
            'data.fcm_token' => ['nullable', 'string', 'max:4096'],
            'data.token' => ['nullable', 'string', 'max:4096'],
            'data.platform' => ['nullable', 'string', 'in:android,ios,web'],
            'data.device_name' => ['nullable', 'string', 'max:120'],
        ];
    }
}
