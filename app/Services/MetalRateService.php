<?php

namespace App\Services;

use App\Models\MetalRate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetalRateService
{
    public function __construct(
        protected AppSettingService $settings,
    ) {}

    public function getLiveRate(string $metalType): float
    {
        $url = $this->settings->get($metalType.'_live_api_url');

        if (filled($url)) {
            $fetched = $this->fetchRateFromApi($metalType, (string) $url);

            if ($fetched !== null) {
                return $fetched;
            }
        }

        $active = $this->getActiveRate($metalType);

        if ($active !== null) {
            return (float) $active->rate_per_gram;
        }

        return $this->settings->getFloat($metalType.'_fallback_rate', $metalType === 'gold' ? 7250 : 85.5);
    }

    public function getLiveRateSource(string $metalType): string
    {
        $url = $this->settings->get($metalType.'_live_api_url');

        if (filled($url)) {
            return 'live_api';
        }

        if ($this->getActiveRate($metalType) !== null) {
            return 'active_rate';
        }

        return 'fallback';
    }

    public function getActiveRate(string $metalType): ?MetalRate
    {
        return MetalRate::query()
            ->where('metal_type', $metalType)
            ->where('is_active', true)
            ->latest()
            ->first();
    }

    public function syncLiveRate(string $metalType): MetalRate
    {
        $rate = $this->getLiveRate($metalType);
        $source = $this->getLiveRateSource($metalType);

        $this->deactivateRates($metalType);

        return MetalRate::create([
            'metal_type' => $metalType,
            'rate_per_gram' => $rate,
            'source' => 'live_sync',
            'is_active' => true,
            'updated_by' => Auth::guard('admin')->id(),
            'notes' => match ($source) {
                'live_api' => 'Synced from configured live API',
                'active_rate' => 'Synced from current active rate (API unavailable)',
                default => 'Synced using fallback rate from settings',
            },
        ]);
    }

    public function applyManualRate(string $metalType, float $rate, bool $isActive, ?string $notes = null): MetalRate
    {
        if ($isActive) {
            $this->deactivateRates($metalType);
        }

        return MetalRate::create([
            'metal_type' => $metalType,
            'rate_per_gram' => $rate,
            'source' => 'manual_override',
            'is_active' => $isActive,
            'updated_by' => Auth::guard('admin')->id(),
            'notes' => $notes,
        ]);
    }

    protected function deactivateRates(string $metalType): void
    {
        MetalRate::query()
            ->where('metal_type', $metalType)
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }

    protected function fetchRateFromApi(string $metalType, string $url): ?float
    {
        try {
            $headers = json_decode((string) $this->settings->get('metal_api_headers', '{}'), true) ?? [];
            $timeout = max(3, $this->settings->getInt('metal_api_timeout_seconds', 10));

            $response = Http::timeout($timeout)
                ->withHeaders(is_array($headers) ? $headers : [])
                ->get($url);

            if (! $response->successful()) {
                Log::warning('Metal rate API failed', [
                    'metal' => $metalType,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $body = $response->json() ?? $response->body();
            $path = (string) $this->settings->get($metalType.'_live_api_json_path', 'price');

            if (is_numeric($body)) {
                return round((float) $body, 2);
            }

            if (! is_array($body)) {
                return null;
            }

            $value = data_get($body, $path);

            if (! is_numeric($value)) {
                Log::warning('Metal rate JSON path not found', [
                    'metal' => $metalType,
                    'path' => $path,
                ]);

                return null;
            }

            return round((float) $value, 2);
        } catch (\Throwable $exception) {
            Log::warning('Metal rate API exception', [
                'metal' => $metalType,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
