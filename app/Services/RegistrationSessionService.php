<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegistrationSessionService
{
    public function __construct(
        protected OtpService $otp,
    ) {}

    public function create(string $phone): array
    {
        $phone = preg_replace('/\D/', '', $phone) ?? $phone;

        if (! $this->otp->phoneWasVerified($phone)) {
            throw ValidationException::withMessages([
                'phone' => ['Mobile number is not verified. Please verify OTP first.'],
            ]);
        }

        $ttl = config('otp.registration_session_ttl', 1800);
        $token = Str::random(64);

        Cache::put($this->sessionCacheKey($token), [
            'phone' => $phone,
            'name' => null,
            'referral_code' => null,
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
                'registration_token' => ['Registration session has expired. Please start again.'],
            ]);
        }

        return $session;
    }

    public function updateProfile(string $token, string $name, ?string $referralCode = null): array
    {
        $session = $this->get($token);
        $ttl = config('otp.registration_session_ttl', 1800);

        $session['name'] = trim($name);
        $session['referral_code'] = filled($referralCode)
            ? strtoupper(trim($referralCode))
            : null;

        Cache::put($this->sessionCacheKey($token), $session, $ttl);

        return $session;
    }

    public function forget(string $token, ?string $phone = null): void
    {
        Cache::forget($this->sessionCacheKey($token));

        if (filled($phone)) {
            $this->otp->clearVerification($phone);
        }
    }

    protected function sessionCacheKey(string $token): string
    {
        return "registration:session:{$token}";
    }
}
