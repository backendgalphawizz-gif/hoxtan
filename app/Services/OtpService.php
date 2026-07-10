<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OtpService
{
    public function sendRegistrationOtp(string $phone): array
    {
        $phone = $this->normalizePhone($phone);

        $alreadyRegistered = User::query()->where('phone', $phone)->exists();

        return $this->sendOtp($phone, 'register', [
            'already_registered' => $alreadyRegistered,
        ]);
    }

    public function sendLoginOtp(string $phone): array
    {
        $phone = $this->normalizePhone($phone);
        $user = $this->findActiveUserByPhone($phone);

        return $this->sendOtp($phone, 'login', [
            'phone' => $phone,
            'has_mpin' => filled($user->mpin),
        ]);
    }

    public function verifyRegistrationOtp(string $phone, string $otp): void
    {
        $this->verifyOtp($phone, $otp, 'register');
        Cache::put(
            $this->verifiedCacheKey($this->normalizePhone($phone), 'register'),
            true,
            config('otp.registration_session_ttl', 1800),
        );
    }

    public function verifyLoginOtp(string $phone, string $otp): void
    {
        $this->verifyOtp($phone, $otp, 'login');
        Cache::put(
            $this->verifiedCacheKey($this->normalizePhone($phone), 'login'),
            true,
            config('otp.registration_session_ttl', 1800),
        );
    }

    public function sendForgotMpinOtp(string $phone): array
    {
        $phone = $this->normalizePhone($phone);
        $user = $this->findActiveUserByPhone($phone);

        return $this->sendOtp($phone, 'forgot-mpin', [
            'phone' => $phone,
            'has_mpin' => filled($user->mpin),
        ]);
    }

    public function verifyForgotMpinOtp(string $phone, string $otp): void
    {
        $this->verifyOtp($phone, $otp, 'forgot-mpin');
        Cache::put(
            $this->verifiedCacheKey($this->normalizePhone($phone), 'forgot-mpin'),
            true,
            config('otp.registration_session_ttl', 1800),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function sendDriverLoginOtp(string $phone): array
    {
        $phone = $this->normalizePhone($phone);
        $driver = $this->findActiveDriverByPhone($phone);

        return $this->sendOtp($phone, 'driver-login', [
            'phone' => $phone,
            'driver_name' => $driver->name,
        ]);
    }

    public function verifyDriverLoginOtp(string $phone, string $otp): void
    {
        $phone = $this->normalizePhone($phone);
        $this->findActiveDriverByPhone($phone);
        $this->verifyOtp($phone, $otp, 'driver-login');
    }

    protected function findActiveDriverByPhone(string $phone): Driver
    {
        $phone = $this->normalizePhone($phone);

        $driver = Driver::query()->where('phone', $phone)->first();

        if (! $driver) {
            throw ValidationException::withMessages([
                'phone' => ['This mobile number is not registered as a driver. Please contact admin.'],
            ]);
        }

        if (! $driver->is_active) {
            throw ValidationException::withMessages([
                'phone' => ['Your driver account is inactive. Please contact admin.'],
            ])->status(403);
        }

        return $driver;
    }

    protected function findActiveUserByPhone(string $phone): User
    {
        $phone = $this->normalizePhone($phone);

        $user = User::query()->where('phone', $phone)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'phone' => ['This mobile number is not registered.'],
            ]);
        }

        if ($user->is_blocked) {
            throw ValidationException::withMessages([
                'phone' => ['Your account has been blocked.'],
            ])->status(403);
        }

        return $user;
    }

    public function phoneWasVerified(string $phone, string $purpose = 'register'): bool
    {
        return (bool) Cache::get($this->verifiedCacheKey($this->normalizePhone($phone), $purpose));
    }

    public function clearVerification(string $phone, string $purpose = 'register'): void
    {
        Cache::forget($this->verifiedCacheKey($this->normalizePhone($phone), $purpose));
    }

    public function markPhoneVerified(string $phone, string $purpose): void
    {
        Cache::put(
            $this->verifiedCacheKey($this->normalizePhone($phone), $purpose),
            true,
            config('otp.registration_session_ttl', 1800),
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function sendOtp(string $phone, string $purpose, array $extra = []): array
    {
        $resendAfter = config('otp.resend_after_seconds', 30);
        $resendKey = $this->resendCacheKey($phone, $purpose);

        if (Cache::has($resendKey)) {
            $retryAfter = max(1, (int) Cache::get($resendKey) - now()->timestamp);

            throw ValidationException::withMessages([
                'phone' => ["Please wait {$retryAfter} seconds before requesting a new OTP."],
            ])->status(429);
        }

        $code = $this->generateCode();
        $expiresIn = config('otp.expires_in_seconds', 300);

        Cache::put($this->otpCacheKey($phone, $purpose), [
            'code' => $code,
            'attempts' => 0,
            'expires_at' => now()->addSeconds($expiresIn)->timestamp,
        ], $expiresIn);

        Cache::put($resendKey, now()->addSeconds($resendAfter)->timestamp, $resendAfter);

        $this->dispatchOtp($phone, $code, $purpose);

        $payload = array_merge([
            'message' => 'OTP sent successfully.',
            'resend_after_seconds' => $resendAfter,
            'expires_in_seconds' => $expiresIn,
        ], $extra);

        if (config('otp.expose_in_response')) {
            $payload['otp'] = $code;
        }

        return $payload;
    }

    protected function verifyOtp(string $phone, string $otp, string $purpose): void
    {
        $phone = $this->normalizePhone($phone);
        $record = Cache::get($this->otpCacheKey($phone, $purpose));

        if (! is_array($record)) {
            throw ValidationException::withMessages([
                'otp' => ['OTP has expired or was not requested. Please request a new OTP.'],
            ]);
        }

        if (($record['expires_at'] ?? 0) < now()->timestamp) {
            Cache::forget($this->otpCacheKey($phone, $purpose));

            throw ValidationException::withMessages([
                'otp' => ['OTP has expired. Please request a new OTP.'],
            ]);
        }

        $maxAttempts = config('otp.max_attempts', 5);
        $attempts = (int) ($record['attempts'] ?? 0);

        if ($attempts >= $maxAttempts) {
            Cache::forget($this->otpCacheKey($phone, $purpose));

            throw ValidationException::withMessages([
                'otp' => ['Too many invalid attempts. Please request a new OTP.'],
            ]);
        }

        if (! hash_equals((string) $record['code'], trim($otp))) {
            $record['attempts'] = $attempts + 1;
            $ttl = max(1, (int) $record['expires_at'] - now()->timestamp);
            Cache::put($this->otpCacheKey($phone, $purpose), $record, $ttl);

            throw ValidationException::withMessages([
                'otp' => ['Invalid OTP. Please try again.'],
            ]);
        }

        Cache::forget($this->otpCacheKey($phone, $purpose));
    }

    protected function dispatchOtp(string $phone, string $code, string $purpose = 'register'): void
    {
        Log::info('OTP generated.', [
            'purpose' => $purpose,
            'phone' => $phone,
            'otp' => $code,
        ]);
    }

    protected function generateCode(): string
    {
        $length = max(4, min(6, (int) config('otp.length', 4)));
        $max = (10 ** $length) - 1;
        $min = 10 ** ($length - 1);

        return (string) random_int($min, $max);
    }

    protected function normalizePhone(string $phone): string
    {
        return preg_replace('/\D/', '', $phone) ?? '';
    }

    protected function otpCacheKey(string $phone, string $purpose = 'register'): string
    {
        return "otp:{$purpose}:{$phone}";
    }

    protected function resendCacheKey(string $phone, string $purpose = 'register'): string
    {
        return "otp:{$purpose}:resend:{$phone}";
    }

    protected function verifiedCacheKey(string $phone, string $purpose = 'register'): string
    {
        return "otp:{$purpose}:verified:{$phone}";
    }
}
