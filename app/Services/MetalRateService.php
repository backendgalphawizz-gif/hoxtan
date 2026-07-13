<?php

namespace App\Services;

use App\Events\MetalRatesUpdated;
use App\Models\MetalRate;
use App\Support\MetalRateRealtimeConfig;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MetalRateService
{
    public function __construct(
        protected AppSettingService $settings,
        protected MetalsApiService $metalsApi,
    ) {}

    public function getLiveRate(string $metalType): float
    {
        $active = $this->getAnyActiveRate($metalType);

        if ($active !== null) {
            return (float) $active->rate_per_gram;
        }

        return $this->getConfigFallbackRate($metalType);
    }

    public function getLiveRateSource(string $metalType): string
    {
        $active = $this->getAnyActiveRate($metalType);

        if ($active !== null) {
            return (string) $active->source;
        }

        return 'fallback';
    }

    public function getActiveRate(string $metalType): ?MetalRate
    {
        return MetalRate::query()
            ->where('metal_type', $metalType)
            ->where('is_active', true)
            ->where('source', 'live_sync')
            ->latest()
            ->first();
    }

    public function getCurrentRatePerGram(string $metalType): float
    {
        return $this->getLiveRate($metalType);
    }

    public function syncLiveRate(string $metalType): MetalRate
    {
        // Only place that should call Metals-API (keep quota low).
        $apiRate = $this->fetchFromMetalsApi($metalType);
        $source = $apiRate !== null ? 'metals_api' : (
            $this->getLastSyncedRate($metalType) !== null ? 'live_sync' : 'fallback'
        );
        $rate = $apiRate
            ?? $this->getLastSyncedRate($metalType)
            ?? $this->getConfigFallbackRate($metalType);

        $this->deactivateRates($metalType);

        Cache::forget('dashboard_metal_rates');
        Cache::forget($this->liveRateCacheKey($metalType));

        if ($apiRate !== null) {
            Cache::put($this->liveRateCacheKey($metalType), $apiRate, $this->liveRateCacheSeconds());
        }

        $record = MetalRate::create([
            'metal_type' => $metalType,
            'rate_per_gram' => $rate,
            'source' => 'live_sync',
            'is_active' => true,
            'updated_by' => Auth::guard('admin')->id(),
            'notes' => match ($source) {
                'metals_api' => 'Synced from Metals-API (live market feed)',
                'live_sync' => 'Synced from last Metals-API fetch',
                default => 'Synced using emergency fallback rate',
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

        if ($isActive) {
            $this->broadcastCurrentRates();
        }

        return $record;
    }

    /**
     * Current buy rates for mobile / WebSocket from DB only (never calls Metals-API).
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
        $active = $this->getAnyActiveRate($metalType);
        $rate = $active !== null
            ? (float) $active->rate_per_gram
            : $this->getConfigFallbackRate($metalType);
        $source = $active?->source ?? 'fallback';
        $updatedAt = $active?->updated_at ?? now();

        return [
            'metal_type' => $metalType,
            'label' => $metalType === 'gold' ? 'Gold' : 'Silver',
            'rate_per_gram' => round((float) $rate, 2),
            'currency' => 'INR',
            'unit' => 'gram',
            'source' => $source,
            'updated_at' => $updatedAt->toIso8601String(),
            'updated_at_display' => $updatedAt->format('d M Y, h:i A'),
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
        if (! MetalRateRealtimeConfig::isEnabled()) {
            return;
        }

        try {
            MetalRatesUpdated::dispatch($this->getApiRates());
        } catch (\Throwable $exception) {
            Log::warning('Metal rate broadcast skipped.', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array{label: string, active: ?float, live: float, active_source: ?string, live_source: string, updated_at: ?string}
     */
    protected function buildMetalSnapshot(string $metalType): array
    {
        // Dashboard uses stored rates only — Metals-API is hit only by metals:sync-live.
        $active = $this->getAnyActiveRate($metalType);
        $synced = $this->getLastSyncedRate($metalType);
        $live = $synced ?? $this->getConfigFallbackRate($metalType);
        $liveSource = $synced !== null ? 'live_sync' : 'fallback';

        return [
            'label' => ucfirst($metalType),
            'active' => $active !== null ? (float) $active->rate_per_gram : null,
            'live' => round((float) $live, 2),
            'active_source' => $active?->source,
            'live_source' => $liveSource,
            'updated_at' => $active?->updated_at?->format('M d, Y g:i A'),
        ];
    }

    protected function getAnyActiveRate(string $metalType): ?MetalRate
    {
        return MetalRate::query()
            ->where('metal_type', $metalType)
            ->where('is_active', true)
            ->latest()
            ->first();
    }

    protected function fetchLiveMarketRate(string $metalType, bool $bypassCache = false): ?float
    {
        if (! $this->metalsApi->isConfigured()) {
            return null;
        }

        $cacheKey = $this->liveRateCacheKey($metalType);

        if (! $bypassCache) {
            $cached = Cache::get($cacheKey);

            if (is_numeric($cached)) {
                return round((float) $cached, 2);
            }
        }

        $rate = $this->fetchFromMetalsApi($metalType);

        if ($rate === null) {
            return null;
        }

        Cache::put($cacheKey, $rate, $this->liveRateCacheSeconds());

        return $rate;
    }

    protected function getLastSyncedRate(string $metalType): ?float
    {
        $active = $this->getActiveRate($metalType);

        return $active !== null ? (float) $active->rate_per_gram : null;
    }

    protected function getConfigFallbackRate(string $metalType): float
    {
        $fallback = config("metal_rates.fallback_rates.{$metalType}");

        if (is_numeric($fallback)) {
            return round((float) $fallback, 2);
        }

        return $metalType === 'gold' ? 7250.0 : 85.5;
    }

    protected function liveRateCacheKey(string $metalType): string
    {
        return "metal_rate_live_{$metalType}";
    }

    protected function liveRateCacheSeconds(): int
    {
        return max(15, (int) config('metal_rates.live_cache_seconds', 60));
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
}
