<?php

namespace App\Console\Commands;

use App\Models\DeviceToken;
use App\Services\FirebaseCloudMessagingService;
use Illuminate\Console\Command;
use Throwable;

class CheckFirebaseCredentialsCommand extends Command
{
    protected $signature = 'firebase:check';

    protected $description = 'Validate Firebase service-account.json can be loaded for FCM';

    public function handle(): int
    {
        $path = (string) config('firebase.credentials');

        $this->newLine();
        $this->info('Firebase credential check');
        $this->line('------------------------');
        $this->line('Path: '.$path);
        $this->line('Exists: '.(is_file($path) ? 'yes' : 'no'));
        $this->line('Readable: '.(is_readable($path) ? 'yes' : 'no'));
        $this->line('Enabled: '.((bool) config('firebase.enabled') ? 'yes' : 'no'));
        $this->line('Project: '.(string) config('firebase.project_id'));

        if (! is_file($path)) {
            $this->error('FAIL: Credentials file missing.');

            return self::FAILURE;
        }

        if (! is_readable($path)) {
            $this->error('FAIL: Credentials file is not readable by PHP. Fix permissions (e.g. chmod 644).');

            return self::FAILURE;
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            $this->error('FAIL: file_get_contents returned false.');

            return self::FAILURE;
        }

        $this->line('Bytes: '.strlen($raw));
        $this->line('Starts with {: '.(str_starts_with(ltrim($raw), '{') ? 'yes' : 'no'));
        $this->line('Has UTF-8 BOM: '.(str_starts_with($raw, "\xEF\xBB\xBF") ? 'yes' : 'no'));

        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $this->warn('Stripping UTF-8 BOM for parse test...');
            $raw = substr($raw, 3);
        }

        $json = json_decode($raw, true);
        if (! is_array($json)) {
            $this->error('FAIL: JSON parse error: '.json_last_error_msg());
            $this->warn('First 120 chars (safe preview): '.substr(preg_replace('/\s+/', ' ', $raw), 0, 120));
            $this->newLine();
            $this->line('Fix: re-upload the Firebase private key JSON without editing it (use binary FTP / scp).');

            return self::FAILURE;
        }

        $this->info('JSON parse: OK');
        $this->line('JSON project_id: '.($json['project_id'] ?? '(missing)'));
        $this->line('Has private_key: '.(filled($json['private_key'] ?? null) ? 'yes' : 'no'));
        $this->line('Has client_email: '.(filled($json['client_email'] ?? null) ? 'yes' : 'no'));

        $privateKey = (string) ($json['private_key'] ?? '');
        if ($privateKey === '' || str_contains($privateKey, 'REPLACE_ME')) {
            $this->error('FAIL: private_key missing or still has REPLACE_ME placeholders.');

            return self::FAILURE;
        }

        if (! str_contains($privateKey, 'BEGIN PRIVATE KEY')) {
            $this->error('FAIL: private_key does not contain BEGIN PRIVATE KEY.');

            return self::FAILURE;
        }

        $this->info('private_key format: OK');

        try {
            $fcm = app(FirebaseCloudMessagingService::class);
            $messaging = $fcm->messaging();
            if ($messaging === null) {
                $this->error('FAIL: Firebase messaging init failed: '.($fcm->lastError() ?? 'unknown'));

                return self::FAILURE;
            }
        } catch (Throwable $e) {
            $this->error('FAIL: Firebase init exception: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Firebase messaging: OK');
        $this->line('Device tokens in DB: '.DeviceToken::query()->count());
        $this->newLine();
        $this->info('RESULT: Firebase credentials OK.');

        return self::SUCCESS;
    }
}
