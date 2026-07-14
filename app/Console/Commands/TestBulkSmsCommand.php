<?php

namespace App\Console\Commands;

use App\Services\BulkSmsService;
use Illuminate\Console\Command;

class TestBulkSmsCommand extends Command
{
    protected $signature = 'sms:test {mobile : 10-digit mobile number} {--otp=1234 : OTP digits to send}';

    protected $description = 'Send a test OTP SMS via BulkSMS and print the API result';

    public function handle(BulkSmsService $sms): int
    {
        $error = $sms->configurationError();
        if ($error !== null) {
            $this->error($error);

            return self::FAILURE;
        }

        $mobile = (string) $this->argument('mobile');
        $otp = (string) $this->option('otp');

        $this->info("Sending OTP {$otp} to {$mobile}...");

        $ok = $sms->sendOtp($mobile, $otp, 'sms-test');

        if ($ok) {
            $this->info('SMS request accepted. Check phone + storage/logs/laravel.log for gateway body.');

            return self::SUCCESS;
        }

        $this->error('SMS send failed. Check storage/logs/laravel.log for BulkSMS response.');

        return self::FAILURE;
    }
}
