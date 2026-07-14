<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class BulkSmsService
{
    public function isConfigured(): bool
    {
        return filled(config('bulksms.authkey'))
            && filled(config('bulksms.sender'))
            && filled(config('bulksms.dlt_te_id'));
    }

    public function isEnabled(): bool
    {
        return (bool) config('bulksms.enabled', true) && $this->isConfigured();
    }

    public function configurationError(): ?string
    {
        if (! config('bulksms.enabled', true)) {
            return 'BulkSMS is disabled (BULKSMS_ENABLED=false).';
        }

        $missing = [];
        if (! filled(config('bulksms.authkey'))) {
            $missing[] = 'BULKSMS_AUTHKEY';
        }
        if (! filled(config('bulksms.sender'))) {
            $missing[] = 'BULKSMS_SENDER';
        }
        if (! filled(config('bulksms.dlt_te_id'))) {
            $missing[] = 'BULKSMS_DLT_TE_ID';
        }

        if ($missing === []) {
            return null;
        }

        return 'BulkSMS not configured. Set in .env: '.implode(', ', $missing);
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
            Log::warning('BulkSMS skipped.', [
                'context' => $context,
                'mobile' => $mobile,
                'reason' => $this->configurationError(),
            ]);

            return false;
        }

        $mobile = preg_replace('/\D/', '', $mobile) ?? '';
        if ($mobile === '') {
            return false;
        }

        // India: API often expects 10-digit OR 91XXXXXXXXXX with country=91
        if (strlen($mobile) === 12 && str_starts_with($mobile, '91')) {
            $mobile = substr($mobile, 2);
        }

        $query = [
            'authkey' => (string) config('bulksms.authkey'),
            'mobiles' => $mobile,
            'sender' => (string) config('bulksms.sender'),
            'route' => (string) config('bulksms.route', '4'),
            'country' => (string) config('bulksms.country', '91'),
            'DLT_TE_ID' => (string) config('bulksms.dlt_te_id'),
            'message' => $message,
        ];

        $url = (string) config('bulksms.base_url');

        try {
            $response = Http::timeout((int) config('bulksms.timeout', 15))
                ->accept('*/*')
                ->withOptions(['allow_redirects' => true])
                ->get($url, $query);

            $body = trim($response->body());

            Log::info('BulkSMS response.', [
                'context' => $context,
                'mobile' => $mobile,
                'status' => $response->status(),
                'body' => $body,
                'url' => $url,
            ]);

            // Gateway usually returns a numeric request id on success.
            if (! $response->successful()) {
                return false;
            }

            $lower = strtolower($body);
            if (
                str_contains($lower, 'invalid')
                || str_contains($lower, 'error')
                || str_contains($lower, 'auth') && str_contains($lower, 'fail')
            ) {
                Log::error('BulkSMS rejected request.', ['body' => $body]);

                return false;
            }

            return $body !== '';
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
