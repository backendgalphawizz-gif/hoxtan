<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckFirebaseCredentialsCommand extends Command
{
    protected $signature = 'firebase:check';

    protected $description = 'Validate Firebase service-account.json can be loaded for FCM';

    public function handle(): int
    {
        $path = (string) config('firebase.credentials');
        $this->line('Path: '.$path);
        $this->line('Exists: '.(is_file($path) ? 'yes' : 'no'));
        $this->line('Enabled: '.((bool) config('firebase.enabled') ? 'yes' : 'no'));
        $this->line('Project: '.(string) config('firebase.project_id'));

        if (! is_file($path)) {
            $this->error('Credentials file missing.');

            return self::FAILURE;
        }

        $raw = (string) file_get_contents($path);
        $this->line('Bytes: '.strlen($raw));

        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $this->warn('File has UTF-8 BOM — will be stripped at runtime, but prefer re-saving as UTF-8 without BOM.');
            $raw = substr($raw, 3);
        }

        $json = json_decode($raw, true);
        if (! is_array($json)) {
            $this->error('JSON parse failed: '.json_last_error_msg());

            return self::FAILURE;
        }

        $privateKey = (string) ($json['private_key'] ?? '');
        if ($privateKey === '' || str_contains($privateKey, 'REPLACE_ME')) {
            $this->error('private_key missing or still has REPLACE_ME placeholders.');

            return self::FAILURE;
        }

        if (! str_contains($privateKey, 'BEGIN PRIVATE KEY')) {
            $this->error('private_key does not contain BEGIN PRIVATE KEY.');

            return self::FAILURE;
        }

        try {
            $fcm = app(\App\Services\FirebaseCloudMessagingService::class);
            $messaging = $fcm->messaging();
            if ($messaging === null) {
                $this->error('Firebase messaging failed: '.($fcm->lastError() ?? 'unknown'));

                return self::FAILURE;
            }
        } catch (\Throwable $e) {
            $this->error('Firebase init exception: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Firebase credentials OK.');
        $this->line('Device tokens in DB: '.\App\Models\DeviceToken::query()->count());

        return self::SUCCESS;
    }
}
