<?php

namespace App\Services;

use App\Models\JewelleryGoldKaratRate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class JewelleryKaratRateService
{
    public const CACHE_KEY = 'jewellery_gold_karat_rates';

    /**
     * Purities that use admin-defined rates instead of metal API rates.
     *
     * @return list<string>
     */
    public function managedPurities(): array
    {
        return array_values(config('jewellery.karat_rate_purities', ['20K', '18K', '16K', '14K']));
    }

    public function usesAdminRate(?string $metalType, ?string $purity): bool
    {
        return $metalType === 'gold'
            && filled($purity)
            && in_array((string) $purity, $this->managedPurities(), true);
    }

    public function getRatePerGram(string $purity): ?float
    {
        $rates = $this->activeRatesByPurity();
        $rate = $rates[$purity] ?? null;

        return $rate !== null ? (float) $rate : null;
    }

    /**
     * @return list<string>
     */
    public function activeManagedPurities(): array
    {
        return array_keys($this->activeRatesByPurity());
    }

    public function isManagedPurityActive(string $purity): bool
    {
        return array_key_exists($purity, $this->activeRatesByPurity());
    }

    /**
     * @return array<string, float>
     */
    public function activeRatesByPurity(): array
    {
        return Cache::remember(self::CACHE_KEY, 300, function (): array {
            return JewelleryGoldKaratRate::query()
                ->where('is_active', true)
                ->whereIn('purity', $this->managedPurities())
                ->where('rate_per_gram', '>', 0)
                ->pluck('rate_per_gram', 'purity')
                ->map(fn ($rate) => (float) $rate)
                ->all();
        });
    }

    /**
     * @return Collection<int, JewelleryGoldKaratRate>
     */
    public function allManaged(): Collection
    {
        $existing = JewelleryGoldKaratRate::query()
            ->whereIn('purity', $this->managedPurities())
            ->get()
            ->keyBy('purity');

        return collect($this->managedPurities())
            ->map(function (string $purity) use ($existing): JewelleryGoldKaratRate {
                return $existing->get($purity) ?? new JewelleryGoldKaratRate([
                    'purity' => $purity,
                    'rate_per_gram' => null,
                    'is_active' => true,
                ]);
            });
    }

    /**
     * @param  array<string, array{rate_per_gram?: mixed, is_active?: mixed}>  $rows
     */
    public function saveRates(array $rows): void
    {
        $adminId = Auth::guard('admin')->id();

        foreach ($this->managedPurities() as $purity) {
            $row = $rows[$purity] ?? null;

            if (! is_array($row) || blank($row['rate_per_gram'] ?? null)) {
                continue;
            }

            JewelleryGoldKaratRate::query()->updateOrCreate(
                ['purity' => $purity],
                [
                    'rate_per_gram' => round((float) $row['rate_per_gram'], 2),
                    'is_active' => (bool) ($row['is_active'] ?? true),
                    'updated_by' => $adminId,
                ],
            );
        }

        $this->forgetCache();
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
