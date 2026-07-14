<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class BulkSmsService
{
    /** @var string|null Last gateway error description for API responses */
    protected ?string $lastError = null;

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function isConfigured(?string $purpose = null): bool
    {
        return filled(config('bulksms.authkey'))
            && filled(config('bulksms.sender'))
            && filled($this->dltTeIdFor($purpose ?? 'login'));
    }

    public function isEnabled(?string $purpose = null): bool
    {
        return (bool) config('bulksms.enabled', true) && $this->isConfigured($purpose);
    }

    public function configurationError(?string $purpose = null): ?string
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
        if (! filled($this->dltTeIdFor($purpose ?? 'login'))) {
            $missing[] = in_array($purpose, ['login', 'otp', 'sms', null], true)
                ? 'BULKSMS_LOGIN_DLT_TE_ID'
                : 'BULKSMS_DLT_TE_ID';
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
        $template = $this->messageFor($purpose);
        $message = str_replace(
            ['{#var#}', '{otp}', '{OTP}'],
            [$otp, $otp, $otp],
            $template,
        );

        return $this->send($mobile, $message, $purpose);
    }

    public function send(string $mobile, string $message, string $context = 'sms'): bool
    {
        $this->lastError = null;

        if (! $this->isEnabled($context)) {
            $this->lastError = $this->configurationError($context);
            Log::warning('BulkSMS skipped.', [
                'context' => $context,
                'mobile' => $mobile,
                'reason' => $this->lastError,
            ]);

            return false;
        }

        $mobile = preg_replace('/\D/', '', $mobile) ?? '';
        if ($mobile === '') {
            $this->lastError = 'Invalid mobile number.';

            return false;
        }

        if (strlen($mobile) === 12 && str_starts_with($mobile, '91')) {
            $mobile = substr($mobile, 2);
        }

        $sender = trim((string) config('bulksms.sender'));
        $dltTeId = $this->dltTeIdFor($context);

        // YourBulkSMS expects sender / senderid (approved DLT header, usually 6 characters).
        $query = [
            'authkey' => (string) config('bulksms.authkey'),
            'mobiles' => $mobile,
            'sender' => $sender,
            'senderid' => $sender,
            'route' => (string) config('bulksms.route', '2'),
            'country' => (string) config('bulksms.country', '0'),
            'DLT_TE_ID' => $dltTeId,
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
                'sender' => $sender,
                'dlt_te_id' => $dltTeId,
                'status' => $response->status(),
                'body' => $body,
                'url' => $url,
            ]);

            if (! $response->successful()) {
                $this->lastError = 'BulkSMS HTTP '.$response->status().': '.$body;

                return false;
            }

            return $this->isSuccessResponse($body);
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            Log::error('BulkSMS send failed.', [
                'context' => $context,
                'mobile' => $mobile,
                'dlt_te_id' => $dltTeId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    protected function isSuccessResponse(string $body): bool
    {
        if ($body === '') {
            $this->lastError = 'Empty response from BulkSMS.';

            return false;
        }

        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            $status = strtolower((string) ($decoded['Status'] ?? $decoded['status'] ?? ''));
            $code = (string) ($decoded['Code'] ?? $decoded['code'] ?? '');
            $description = (string) ($decoded['Description'] ?? $decoded['description'] ?? $body);

            if ($status === 'success' || $code === '000') {
                return true;
            }

            $this->lastError = $description !== '' ? $description : $body;
            Log::error('BulkSMS rejected request.', ['body' => $body]);

            return false;
        }

        $lower = strtolower($body);
        if (
            str_contains($lower, 'invalid')
            || str_contains($lower, 'failed')
            || str_contains($lower, 'error')
        ) {
            $this->lastError = $body;
            Log::error('BulkSMS rejected request.', ['body' => $body]);

            return false;
        }

        // Legacy gateways return a numeric request id on success.
        return true;
    }

    public function dltTeIdFor(string $purpose): string
    {
        $purposeKey = $this->normalizePurpose($purpose);
        $specific = config("bulksms.templates.{$purposeKey}.dlt_te_id");

        if (filled($specific)) {
            return (string) $specific;
        }

        return (string) config('bulksms.dlt_te_id', '');
    }

    protected function messageFor(string $purpose): string
    {
        $purposeKey = $this->normalizePurpose($purpose);
        $specific = config("bulksms.templates.{$purposeKey}.message");

        if (filled($specific)) {
            return (string) $specific;
        }

        return (string) config('bulksms.otp_message', 'Your OTP is {otp}. Do not share it with anyone.');
    }

    protected function normalizePurpose(string $purpose): string
    {
        return match ($purpose) {
            'login', 'otp', 'sms', 'sms-test' => 'login',
            'register' => 'register',
            'forgot-mpin' => 'forgot-mpin',
            'driver-login' => 'driver-login',
            default => $purpose,
        };
    }
}
