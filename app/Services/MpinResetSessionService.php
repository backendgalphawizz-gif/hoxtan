<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MpinResetSessionService
{
    public function __construct(
        protected OtpService $otp,
    ) {}

    public function create(string $phone): array
    {
        $phone = preg_replace('/\D/', '', $phone) ?? $phone;

        if (! $this->otp->phoneWasVerified($phone, 'forgot-mpin')) {
            throw ValidationException::withMessages([
                'phone' => ['Mobile number is not verified. Please verify OTP first.'],
            ]);
        }

        $ttl = config('otp.registration_session_ttl', 1800);
        $token = Str::random(64);

        Cache::put($this->sessionCacheKey($token), [
            'phone' => $phone,
            'created_at' => now()->timestamp,
        ], $ttl);

        return [
            'token' => $token,
            'expires_in' => $ttl,
        ];
    }

    public function get(string $token): array
    {
        $session = Cache::get($this->sessionCacheKey($token));

        if (! is_array($session)) {
            throw ValidationException::withMessages([
                'reset_token' => ['M-PIN reset session has expired. Please start again.'],
            ]);
        }

        return $session;
    }

    public function forget(string $token, ?string $phone = null): void
    {
        Cache::forget($this->sessionCacheKey($token));

        if (filled($phone)) {
            $this->otp->clearVerification($phone, 'forgot-mpin');
        }
    }

    protected function sessionCacheKey(string $token): string
    {
        return "forgot-mpin:session:{$token}";
    }
}
