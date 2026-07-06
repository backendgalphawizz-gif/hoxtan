<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class NormalizeUserMpins extends Command
{
    protected $signature = 'mpin:normalize {--phone= : Normalize a single user by phone}';

    protected $description = 'Convert encrypted M-PINs to plain storage so login APIs can return them';

    public function handle(): int
    {
        $query = User::query()->whereNotNull('mpin');

        if ($phone = $this->option('phone')) {
            $phone = preg_replace('/\D/', '', $phone) ?? $phone;
            $query->where('phone', $phone);
        }

        $converted = 0;
        $legacy = 0;

        $query->each(function (User $user) use (&$converted, &$legacy): void {
            $raw = $user->getRawOriginal('mpin');

            if (blank($raw)) {
                return;
            }

            if ($user->usesLegacyHashedMpin()) {
                $legacy++;
                $this->warn("Legacy bcrypt M-PIN (cannot auto-convert): {$user->phone} — reset M-PIN in admin or use Forgot M-PIN.");

                return;
            }

            if ($user->usesEncryptedMpinStorage()) {
                $plain = $user->readableMpin();

                if (blank($plain)) {
                    $this->error("Could not decrypt M-PIN for {$user->phone}.");

                    return;
                }

                $user->forceFill(['mpin' => $plain])->saveQuietly();
                $converted++;
                $this->info("Converted encrypted M-PIN to plain storage: {$user->phone}");

                return;
            }

            $this->line("Already plain storage: {$user->phone}");
        });

        $this->newLine();
        $this->info("Converted: {$converted}");
        $this->info("Legacy bcrypt (manual reset required): {$legacy}");

        return self::SUCCESS;
    }
}
