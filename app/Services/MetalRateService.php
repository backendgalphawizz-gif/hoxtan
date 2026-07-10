<?php

namespace App\Services;

use App\Events\MetalRatesUpdated;
use App\Models\MetalRate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetalRateService
{
    public function __construct(
        protected AppSettingService $settings,
        protected MetalsApiService $metalsApi,
    ) {}

    public function getLiveRate(string $metalType): float
    {
        if ($this->metalsApi->isConfigured()) {
            $rate = $this->fetchFromMetalsApi($metalType);

            if ($rate !== null) {
                return $rate;
            }
        }

        $url = $this->settings->get($metalType.'_live_api_url');

        if (filled($url)) {
            $fetched = $this->fetchRateFromCustomApi($metalType, (string) $url);

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
        if ($this->metalsApi->isConfigured()) {
            $rate = $this->fetchFromMetalsApi($metalType);

            if ($rate !== null) {
                return 'metals_api';
            }
        }

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

    public function getCurrentRatePerGram(string $metalType): float
    {
        $active = $this->getActiveRate($metalType);

        if ($active !== null) {
            return (float) $active->rate_per_gram;
        }

        return $this->getLiveRate($metalType);
    }

    public function syncLiveRate(string $metalType): MetalRate
    {
        $rate = $this->getLiveRate($metalType);
        $source = $this->getLiveRateSource($metalType);

        $this->deactivateRates($metalType);

        Cache::forget('dashboard_metal_rates');

        $record = MetalRate::create([
            'metal_type' => $metalType,
            'rate_per_gram' => $rate,
            'source' => 'live_sync',
            'is_active' => true,
            'updated_by' => Auth::guard('admin')->id(),
            'notes' => match ($source) {
                'metals_api' => 'Synced from Metals-API (live market feed)',
                'live_api' => 'Synced from configured custom API URL',
                'active_rate' => 'Synced from current active rate (API unavailable)',
                default => 'Synced using fallback rate from settings',
            },
        ]);

        $this->broadcastCurrentRates();

        return $record;
    }

    public function applyManualRate(string $metalType, float $rate, bool $isActive, ?string $notes = null): MetalRate
    {
        if ($isActive) {
            $this->deactivateRates($metalType);
        }

        Cache::forget('dashboard_metal_rates');

        $record = MetalRate::create([
            'metal_type' => $metalType,
            'rate_per_gram' => $rate,
            'source' => 'manual_override',
            'is_active' => $isActive,
            'updated_by' => Auth::guard('admin')->id(),
            'notes' => $notes,
        ]);

        $this->broadcastCurrentRates();

        return $record;
    }

    /**
     * Current buy rates for the mobile app (active override, else live/fallback).
     *
     * @return array{currency: string, unit: string, fetched_at: string, rates: list<array>}
     */
    public function getApiRates(?string $metalType = null): array
    {
        $types = $metalType !== null
            ? [$metalType]
            : ['gold', 'silver'];

        $rates = collect($types)
            ->map(fn (string $type) => $this->buildApiRatePayload($type))
            ->values()
            ->all();

        return [
            'currency' => 'INR',
            'unit' => 'gram',
            'fetched_at' => now()->toIso8601String(),
            'fetched_at_display' => now()->format('d M Y, h:i A'),
            'rates' => $rates,
            'gold' => collect($rates)->firstWhere('metal_type', 'gold'),
            'silver' => collect($rates)->firstWhere('metal_type', 'silver'),
        ];
    }

    /**
     * @return array{
     *     metal_type: string,
     *     label: string,
     *     rate_per_gram: float,
     *     currency: string,
     *     unit: string,
     *     source: string,
     *     updated_at: ?string,
     *     updated_at_display: ?string
     * }
     */
    protected function buildApiRatePayload(string $metalType): array
    {
        $active = $this->getActiveRate($metalType);
        $rate = $this->getCurrentRatePerGram($metalType);
        $source = $active?->source ?? $this->getLiveRateSource($metalType);
        $updatedAt = $active?->updated_at ?? now();

        return [
            'metal_type' => $metalType,
            'label' => $metalType === 'gold' ? 'Gold' : 'Silver',
            'rate_per_gram' => round((float) $rate, 2),
            'currency' => 'INR',
            'unit' => 'gram',
            'source' => $source,
            'updated_at' => $updatedAt?->toIso8601String(),
            'updated_at_display' => $updatedAt?->format('d M Y, h:i A'),
        ];
    }

    /**
     * @return array{gold: array, silver: array, fetched_at: string}
     */
    public function getDashboardRates(): array
    {
        return Cache::remember('dashboard_metal_rates', 120, function (): array {
            return [
                'gold' => $this->buildMetalSnapshot('gold'),
                'silver' => $this->buildMetalSnapshot('silver'),
                'fetched_at' => now()->format('M d, Y g:i A'),
            ];
        });
    }

    public function forgetDashboardRatesCache(): void
    {
        Cache::forget('dashboard_metal_rates');
    }

    public function broadcastCurrentRates(): void
    {
        if (! $this->realtimeBroadcastingEnabled()) {
            return;
        }

        MetalRatesUpdated::dispatch($this->getApiRates());
    }

    protected function realtimeBroadcastingEnabled(): bool
    {
        $driver = (string) config('broadcasting.default', 'null');

        if (! in_array($driver, ['reverb', 'pusher', 'log'], true)) {
            return false;
        }

        if ($driver === 'log') {
            return true;
        }

        $connection = config("broadcasting.connections.{$driver}", []);

        return is_array($connection) && filled($connection['key'] ?? null);
    }

    /**
     * @return array{label: string, active: ?float, live: float, active_source: ?string, live_source: string, updated_at: ?string}
     */
    protected function buildMetalSnapshot(string $metalType): array
    {
        $active = $this->getActiveRate($metalType);
        $live = null;
        $liveSource = 'fallback';

        if ($this->metalsApi->isConfigured()) {
            $live = $this->fetchFromMetalsApi($metalType);

            if ($live !== null) {
                $liveSource = 'metals_api';
            }
        }

        $url = $this->settings->get($metalType.'_live_api_url');

        if ($live === null && filled($url)) {
            $live = $this->fetchRateFromCustomApi($metalType, (string) $url);

            if ($live !== null) {
                $liveSource = 'live_api';
            }
        }

        if ($live === null && $active !== null) {
            $live = (float) $active->rate_per_gram;
            $liveSource = 'active_rate';
        }

        if ($live === null) {
            $live = $this->settings->getFloat(
                $metalType.'_fallback_rate',
                $metalType === 'gold' ? 7250 : 85.5,
            );
        }

        return [
            'label' => ucfirst($metalType),
            'active' => $active !== null ? (float) $active->rate_per_gram : null,
            'live' => round((float) $live, 2),
            'active_source' => $active?->source,
            'live_source' => $liveSource,
            'updated_at' => $active?->updated_at?->format('M d, Y g:i A'),
        ];
    }

    protected function fetchFromMetalsApi(string $metalType): ?float
    {
        return match ($metalType) {
            'gold' => $this->metalsApi->fetchGoldRatePerGram(),
            'silver' => $this->metalsApi->fetchSilverRatePerGram(),
            default => null,
        };
    }

    protected function deactivateRates(string $metalType): void
    {
        MetalRate::query()
            ->where('metal_type', $metalType)
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }

    protected function fetchRateFromCustomApi(string $metalType, string $url): ?float
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
