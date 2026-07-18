<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\DeviceToken;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Throwable;

class FirebaseCloudMessagingService
{
    private ?Messaging $messaging = null;

    private bool $bootAttempted = false;

    private ?string $lastError = null;

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function isEnabled(): bool
    {
        return (bool) config('firebase.enabled', true) && $this->messaging() !== null;
    }

    public function messaging(): ?Messaging
    {
        if ($this->bootAttempted) {
            return $this->messaging;
        }

        $this->bootAttempted = true;
        $this->lastError = null;

        if (! (bool) config('firebase.enabled', true)) {
            $this->lastError = 'FIREBASE_ENABLED is false';
            Log::warning('Firebase push disabled (FIREBASE_ENABLED=false)');

            return null;
        }

        $credentials = (string) config('firebase.credentials');
        if ($credentials === '' || ! is_file($credentials)) {
            $this->lastError = 'Credentials file missing: '.$credentials;
            Log::warning('Firebase credentials file missing — driver/user push will not send', [
                'path' => $credentials,
            ]);

            return null;
        }

        $json = json_decode((string) file_get_contents($credentials), true);
        if (! is_array($json)) {
            $this->lastError = 'service-account.json could not be parsed as JSON ('.$credentials.')';
            Log::warning('Firebase credentials JSON parse failed', ['path' => $credentials]);

            return null;
        }

        $privateKey = (string) ($json['private_key'] ?? '');
        if ($privateKey === '' || str_contains($privateKey, 'REPLACE_ME') || str_contains((string) ($json['client_id'] ?? ''), 'REPLACE_ME')) {
            $this->lastError = 'service-account.json still has placeholder values. Download a real key from Firebase Console → Project settings → Service accounts.';
            Log::warning('Firebase credentials still contain placeholders', [
                'path' => $credentials,
                'realpath' => realpath($credentials) ?: $credentials,
            ]);

            return null;
        }

        if (! str_contains($privateKey, 'BEGIN PRIVATE KEY')) {
            $this->lastError = 'service-account.json private_key is missing BEGIN PRIVATE KEY block';
            Log::warning('Firebase credentials private_key malformed', ['path' => $credentials]);

            return null;
        }

        try {
            $factory = (new Factory)->withServiceAccount($credentials);
            $projectId = config('firebase.project_id');
            if (is_string($projectId) && $projectId !== '') {
                $factory = $factory->withProjectId($projectId);
            }
            $this->messaging = $factory->createMessaging();
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            Log::error('Firebase init failed', ['error' => $e->getMessage()]);
            $this->messaging = null;
        }

        return $this->messaging;
    }

    /**
     * Register or refresh an FCM device token for user / admin / driver.
     */
    public function registerToken(Model $owner, string $token, ?string $platform = null, ?string $deviceName = null): DeviceToken
    {
        $token = trim($token);
        if ($token === '') {
            throw new \InvalidArgumentException('FCM token is empty.');
        }

        if (! $owner->exists || blank($owner->getKey())) {
            throw new \InvalidArgumentException('Cannot register FCM token for an unsaved owner.');
        }

        $platform = is_string($platform) && $platform !== '' ? strtolower(trim($platform)) : null;
        $deviceName = is_string($deviceName) && trim($deviceName) !== '' ? trim($deviceName) : null;

        $hash = hash('sha256', $token);
        $columns = Schema::getColumnListing('device_tokens');
        $hasFcmToken = in_array('fcm_token', $columns, true);
        $hasToken = in_array('token', $columns, true);

        if (! $hasFcmToken && ! $hasToken) {
            throw new \RuntimeException('device_tokens table is missing fcm_token/token column.');
        }

        DeviceToken::query()
            ->where('token_hash', $hash)
            ->where(function ($q) use ($owner) {
                $q->where('tokenable_type', '!=', $owner::class)
                    ->orWhere('tokenable_id', '!=', $owner->getKey());
            })
            ->delete();

        $payload = [
            'platform' => $platform,
            'device_name' => $deviceName,
            'last_used_at' => now(),
        ];

        // Keep both columns in sync when legacy `token` still exists (e.g. SQLite rename path).
        if ($hasFcmToken) {
            $payload['fcm_token'] = $token;
        }
        if ($hasToken) {
            $payload['token'] = $token;
        }

        $device = DeviceToken::query()->updateOrCreate(
            [
                'tokenable_type' => $owner::class,
                'tokenable_id' => $owner->getKey(),
                'token_hash' => $hash,
            ],
            $payload,
        );

        // Guard against mass-assignment / schema-cache misses leaving the token blank.
        if ($hasFcmToken && blank($device->fcm_token)) {
            $device->forceFill(['fcm_token' => $token])->save();
        }
        if ($hasToken && blank($device->getAttribute('token'))) {
            $device->forceFill(['token' => $token])->save();
        }

        return $device->fresh() ?? $device;
    }

    public function removeToken(Model $owner, string $token): void
    {
        $token = trim($token);
        $hash = hash('sha256', $token);

        DeviceToken::query()
            ->where('tokenable_type', $owner::class)
            ->where('tokenable_id', $owner->getKey())
            ->where(function ($q) use ($token, $hash) {
                $q->where('token_hash', $hash);
                if ($this->hasFcmTokenColumn()) {
                    $q->orWhere('fcm_token', $token);
                }
                if (Schema::hasColumn('device_tokens', 'token')) {
                    $q->orWhere('token', $token);
                }
            })
            ->delete();
    }

    /**
     * @return list<string>
     */
    public function tokensFor(Model $owner): array
    {
        $query = DeviceToken::query()
            ->where('tokenable_type', $owner::class)
            ->where('tokenable_id', $owner->getKey());

        if ($this->hasFcmTokenColumn()) {
            return $query->pluck('fcm_token')->filter()->unique()->values()->all();
        }

        if (Schema::hasColumn('device_tokens', 'token')) {
            return $query->pluck('token')->filter()->unique()->values()->all();
        }

        return [];
    }

    /**
     * @param  Collection<int, User|Admin|Driver>|array<int, User|Admin|Driver>  $recipients
     * @param  array<string, mixed>  $data
     * @return array{success: int, failure: int, tokens: int, firebase_ready: bool, error: ?string}
     */
    public function sendToOwners(iterable $recipients, string $title, string $body, array $data = [], ?string $type = null): array
    {
        $tokens = collect();
        foreach ($recipients as $recipient) {
            if ($recipient instanceof Model) {
                $tokens = $tokens->merge($this->tokensFor($recipient));
            }
        }

        if ($type !== null) {
            $data['type'] = $type;
        }

        $unique = $tokens->filter()->unique()->values()->all();

        $recipientMeta = collect($recipients)
            ->filter(fn ($r) => $r instanceof Model)
            ->map(fn (Model $r) => [
                'type' => $r::class,
                'id' => $r->getKey(),
            ])
            ->values()
            ->all();

        if ($unique === []) {
            Log::warning('FCM skipped: no device tokens for recipients', [
                'title' => $title,
                'type' => $type,
                'recipients' => $recipientMeta,
            ]);
        }

        $result = $this->sendToTokens($unique, $title, $body, $data);

        Log::info('FCM sendToOwners result', [
            'title' => $title,
            'type' => $type,
            'recipients' => $recipientMeta,
            'tokens' => count($unique),
            'success' => $result['success'] ?? 0,
            'failure' => $result['failure'] ?? 0,
            'firebase_ready' => $this->messaging() !== null,
            'error' => $this->lastError,
        ]);

        return array_merge($result, [
            'tokens' => count($unique),
            'firebase_ready' => $this->messaging() !== null,
            'error' => $this->lastError,
        ]);
    }

    /**
     * @param  list<string>  $tokens
     * @param  array<string, mixed>  $data
     * @return array{success: int, failure: int}
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): array
    {
        $tokens = array_values(array_filter(array_unique($tokens)));
        if ($tokens === []) {
            return ['success' => 0, 'failure' => 0];
        }

        $messaging = $this->messaging();
        if ($messaging === null) {
            $this->lastError ??= 'Firebase messaging is not ready';
            Log::warning('FCM skipped: Firebase not ready', [
                'token_count' => count($tokens),
                'title' => $title,
                'error' => $this->lastError,
            ]);

            return ['success' => 0, 'failure' => count($tokens)];
        }

        $stringData = [];
        foreach ($data as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $stringData[(string) $key] = $value === null ? '' : (string) $value;
            } else {
                $stringData[(string) $key] = json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
            }
        }

        $channelId = (string) config('firebase.android_channel_id', 'hoxtan_default');

        $buildMessage = function (?string $registrationToken = null) use ($title, $body, $stringData, $channelId): CloudMessage {
            $message = CloudMessage::new()
                ->withNotification(Notification::create($title, $body))
                ->withData($stringData)
                ->withAndroidConfig(AndroidConfig::fromArray([
                    'priority' => 'high',
                    'notification' => [
                        'channel_id' => $channelId,
                        'sound' => 'default',
                        'default_vibrate_timings' => true,
                    ],
                ]))
                ->withApnsConfig(ApnsConfig::fromArray([
                    'headers' => [
                        'apns-priority' => '10',
                    ],
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                            'content-available' => 1,
                            'alert' => [
                                'title' => $title,
                                'body' => $body,
                            ],
                        ],
                    ],
                ]));

            if ($registrationToken !== null) {
                $message = $message->withToken($registrationToken);
            }

            return $message;
        };

        $message = $buildMessage();

        $success = 0;
        $failure = 0;
        $invalid = [];
        $errors = [];

        foreach (array_chunk($tokens, 500) as $chunk) {
            try {
                $report = $messaging->sendMulticast($message, $chunk);
                $success += $report->successes()->count();
                $failure += $report->failures()->count();
                $invalid = array_merge($invalid, $report->unknownTokens(), $report->invalidTokens());

                if ($report->hasFailures()) {
                    $errors[] = 'FCM rejected '.$report->failures()->count().' token(s)';
                }
            } catch (MessagingException $e) {
                $errors[] = $e->getMessage();
                Log::error('FCM multicast failed', ['error' => $e->getMessage()]);
                // Fallback: send one-by-one
                foreach ($chunk as $registrationToken) {
                    try {
                        $messaging->send($buildMessage($registrationToken));
                        $success++;
                    } catch (Throwable $inner) {
                        $failure++;
                        $errors[] = $inner->getMessage();
                        Log::warning('FCM single send failed', [
                            'error' => $inner->getMessage(),
                        ]);
                    }
                }
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
                Log::error('FCM send failed', ['error' => $e->getMessage()]);
                $failure += count($chunk);
            }
        }

        if ($errors !== []) {
            $this->lastError = $errors[0];
        }

        // Only prune tokens Firebase explicitly marks invalid/unknown — never on auth/config errors.
        if ($invalid !== [] && $success + $failure > 0) {
            $invalid = array_values(array_unique($invalid));
            if ($this->hasFcmTokenColumn()) {
                DeviceToken::query()->whereIn('fcm_token', $invalid)->delete();
            } elseif (Schema::hasColumn('device_tokens', 'token')) {
                DeviceToken::query()->whereIn('token', $invalid)->delete();
            }
        }

        return ['success' => $success, 'failure' => $failure];
    }

    protected function hasFcmTokenColumn(): bool
    {
        return Schema::hasColumn('device_tokens', 'fcm_token');
    }
}
