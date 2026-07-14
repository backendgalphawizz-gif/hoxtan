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
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Throwable;

class FirebaseCloudMessagingService
{
    private ?Messaging $messaging = null;

    private bool $bootAttempted = false;

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

        if (! (bool) config('firebase.enabled', true)) {
            Log::warning('Firebase push disabled (FIREBASE_ENABLED=false)');

            return null;
        }

        $credentials = (string) config('firebase.credentials');
        if ($credentials === '' || ! is_file($credentials)) {
            Log::warning('Firebase credentials file missing — driver/user push will not send', [
                'path' => $credentials,
            ]);

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

        $hash = hash('sha256', $token);

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

        if ($this->hasFcmTokenColumn()) {
            $payload['fcm_token'] = $token;
        } else {
            $payload['token'] = $token;
        }

        return DeviceToken::query()->updateOrCreate(
            [
                'tokenable_type' => $owner::class,
                'tokenable_id' => $owner->getKey(),
                'token_hash' => $hash,
            ],
            $payload,
        );
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
     * @return array{success: int, failure: int, tokens: int, firebase_ready: bool}
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

        if ($unique === []) {
            Log::warning('FCM skipped: no device tokens for recipients', [
                'title' => $title,
                'type' => $type,
            ]);
        }

        $result = $this->sendToTokens($unique, $title, $body, $data);

        return array_merge($result, [
            'tokens' => count($unique),
            'firebase_ready' => $this->messaging() !== null,
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
            Log::warning('FCM skipped: Firebase not ready', [
                'token_count' => count($tokens),
                'title' => $title,
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

        $message = CloudMessage::new()
            ->withNotification(Notification::create($title, $body))
            ->withData($stringData);

        $success = 0;
        $failure = 0;
        $invalid = [];

        foreach (array_chunk($tokens, 500) as $chunk) {
            try {
                $report = $messaging->sendMulticast($message, $chunk);
                $success += $report->successes()->count();
                $failure += $report->failures()->count();
                $invalid = array_merge($invalid, $report->unknownTokens(), $report->invalidTokens());
            } catch (MessagingException $e) {
                Log::error('FCM multicast failed', ['error' => $e->getMessage()]);
                // Fallback: send one-by-one
                foreach ($chunk as $registrationToken) {
                    try {
                        $messaging->send(
                            CloudMessage::new()
                                ->withToken($registrationToken)
                                ->withNotification(Notification::create($title, $body))
                                ->withData($stringData)
                        );
                        $success++;
                    } catch (Throwable $inner) {
                        $failure++;
                        Log::warning('FCM single send failed', [
                            'error' => $inner->getMessage(),
                        ]);
                    }
                }
            } catch (Throwable $e) {
                Log::error('FCM send failed', ['error' => $e->getMessage()]);
                $failure += count($chunk);
            }
        }

        if ($invalid !== []) {
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
