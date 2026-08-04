<?php

namespace App\Support;

class PushDispatchFeedback
{
    /**
     * @param  array{recipients?: int, push_tokens?: int, push_success?: int, push_failure?: int, firebase_ready?: bool, error?: ?string}  $result
     * @return array{title: string, body: string, success: bool}
     */
    public static function fromResult(array $result): array
    {
        $recipients = (int) ($result['recipients'] ?? 0);
        $tokens = (int) ($result['push_tokens'] ?? 0);
        $pushSuccess = (int) ($result['push_success'] ?? 0);
        $firebaseReady = (bool) ($result['firebase_ready'] ?? false);
        $error = filled($result['error'] ?? null) ? (string) $result['error'] : null;

        if ($recipients === 0) {
            return [
                'title' => 'Notification saved, but no recipients',
                'body' => 'No matching users/drivers were found for this target.',
                'success' => false,
            ];
        }

        if (! $firebaseReady) {
            return [
                'title' => 'In-app notification saved (push not configured)',
                'body' => 'Saved for '.$recipients.' recipient(s), but Firebase is not ready. '
                    .($error ?? 'Check storage/app/firebase/service-account.json and FIREBASE_ENABLED=true.'),
                'success' => false,
            ];
        }

        if ($tokens === 0) {
            return [
                'title' => 'In-app notification saved (no device tokens)',
                'body' => "Saved for {$recipients} recipient(s), but no FCM device tokens are registered. "
                    .'Ask the user/driver to fully close and reopen the app (or log in again) so it registers POST /device-token.',
                'success' => false,
            ];
        }

        if ($pushSuccess === 0) {
            return [
                'title' => 'In-app notification saved (push failed)',
                'body' => "Saved for {$recipients} recipient(s). Found {$tokens} device token(s), but FCM delivery failed."
                    .($error ? ' Error: '.$error : ' Check storage/logs.'),
                'success' => false,
            ];
        }

        return [
            'title' => 'Push notification sent',
            'body' => "In-app: {$recipients} · Push delivered: {$pushSuccess}/{$tokens} device(s).",
            'success' => true,
        ];
    }
}
