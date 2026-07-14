<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class BulkSmsService
{
    public function isEnabled(): bool
    {
        return (bool) config('bulksms.enabled', true)
            && filled(config('bulksms.authkey'));
    }

    /**
     * Send OTP SMS via yourbulksms HTTP API.
     */
    public function sendOtp(string $mobile, string $otp, string $purpose = 'otp'): bool
    {
        $template = (string) config('bulksms.otp_message', 'Your OTP is {otp}. Do not share it with anyone.');
        $message = str_replace('{otp}', $otp, $template);

        return $this->send($mobile, $message, $purpose);
    }

    public function send(string $mobile, string $message, string $context = 'sms'): bool
    {
        if (! $this->isEnabled()) {
            Log::warning('BulkSMS skipped (disabled or missing authkey).', [
                'context' => $context,
                'mobile' => $mobile,
            ]);

            return false;
        }

        $mobile = preg_replace('/\D/', '', $mobile) ?? '';
        if ($mobile === '') {
            return false;
        }

        $query = [
            'authkey' => (string) config('bulksms.authkey'),
            'mobiles' => $mobile,
            'sender' => (string) config('bulksms.sender'),
            'route' => (string) config('bulksms.route', '2'),
            'country' => (string) config('bulksms.country', '0'),
            'DLT_TE_ID' => (string) config('bulksms.dlt_te_id'),
            'message' => $message,
        ];

        $url = (string) config('bulksms.base_url');

        try {
            $response = Http::timeout((int) config('bulksms.timeout', 15))
                ->accept('*/*')
                ->get($url, $query);

            Log::info('BulkSMS OTP dispatched.', [
                'context' => $context,
                'mobile' => $mobile,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return $response->successful();
        } catch (Throwable $e) {
            Log::error('BulkSMS send failed.', [
                'context' => $context,
                'mobile' => $mobile,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
