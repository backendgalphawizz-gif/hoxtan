<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetalsApiService
{
    public const TROY_OUNCE_GRAMS = 31.1034768;

    public function isConfigured(): bool
    {
        return filled($this->apiKey());
    }

    public function fetchGoldRatePerGram(): ?float
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $symbol = $this->goldSymbol();

        $rate = $this->fetchGoldFromIndiaEndpoint($symbol);

        if ($rate !== null) {
            return $rate;
        }

        return $this->fetchGoldFromLatestEndpoint($symbol);
    }

    public function fetchSilverRatePerGram(): ?float
    {
        if (! $this->isConfigured()) {
            return null;
        }

        return $this->fetchSilverFromLatestGram();
    }

    protected function fetchGoldFromIndiaEndpoint(string $symbol): ?float
    {
        try {
            $response = Http::timeout($this->timeout())
                ->get($this->baseUrl().'/gold-price-india', [
                    'access_key' => $this->apiKey(),
                    'symbols' => $symbol,
                ]);

            if (! $response->successful() || ! $response->json('success')) {
                Log::info('Metals-API gold-price-india unavailable, using /latest fallback', [
                    'status' => $response->status(),
                    'error' => data_get($response->json(), 'error.type'),
                ]);

                return null;
            }

            $rate = data_get($response->json(), "rates.{$symbol}");

            if (! is_numeric($rate)) {
                Log::warning('Metals-API gold symbol missing from gold-price-india response', ['symbol' => $symbol]);

                return null;
            }

            return $this->normalizeIndianGoldPrice((float) $rate);
        } catch (\Throwable $exception) {
            Log::warning('Metals-API gold-price-india exception', ['message' => $exception->getMessage()]);

            return null;
        }
    }

    protected function fetchGoldFromLatestEndpoint(string $symbol): ?float
    {
        try {
            $response = Http::timeout($this->timeout())
                ->get($this->baseUrl().'/latest', [
                    'access_key' => $this->apiKey(),
                    'symbols' => $symbol,
                ]);

            if (! $response->successful() || ! $response->json('success')) {
                Log::warning('Metals-API gold latest fallback failed', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                return null;
            }

            $rates = $response->json('rates', []);
            $usdSymbolKey = 'USD'.$symbol;
            $rate = $rates[$usdSymbolKey] ?? null;

            if (! is_numeric($rate)) {
                Log::warning('Metals-API gold latest missing INR price key', [
                    'symbol' => $symbol,
                    'expected_key' => $usdSymbolKey,
                    'available_keys' => array_keys($rates),
                ]);

                return null;
            }

            // rates.{symbol} is an inverse value (e.g. 6.9e-5), not INR/gram.
            return $this->normalizeIndianGoldPrice((float) $rate);
        } catch (\Throwable $exception) {
            Log::warning('Metals-API gold latest exception', ['message' => $exception->getMessage()]);

            return null;
        }
    }

    protected function fetchSilverFromLatestGram(): ?float
    {
        try {
            $response = Http::timeout($this->timeout())
                ->get($this->baseUrl().'/latest', [
                    'access_key' => $this->apiKey(),
                    'base' => $this->currency(),
                    'symbols' => 'XAG',
                    'unit' => 'Gram',
                ]);

            if (! $response->successful() || ! $response->json('success')) {
                Log::warning('Metals-API silver latest failed', ['status' => $response->status()]);

                return null;
            }

            $rate = data_get($response->json(), 'rates.XAG');

            if (! is_numeric($rate)) {
                return null;
            }

            return round((float) $rate, 2);
        } catch (\Throwable $exception) {
            Log::warning('Metals-API silver latest exception', ['message' => $exception->getMessage()]);

            return null;
        }
    }

    protected function normalizeIndianGoldPrice(float $price): float
    {
        $divisor = max(1, (int) config('services.metals_api.gold_price_divisor', 1));

        return round($price / $divisor, 2);
    }

    protected function apiKey(): ?string
    {
        return config('services.metals_api.key') ?: null;
    }

    protected function baseUrl(): string
    {
        return rtrim((string) config('services.metals_api.base_url', 'https://metals-api.com/api'), '/');
    }

    protected function goldSymbol(): string
    {
        return (string) (app(AppSettingService::class)->get('metals_api_gold_symbol')
            ?: config('services.metals_api.gold_symbol', 'VISA-24k'));
    }

    protected function currency(): string
    {
        return strtoupper((string) config('services.metals_api.currency', 'INR'));
    }

    protected function timeout(): int
    {
        return max(3, app(AppSettingService::class)->getInt('metal_api_timeout_seconds', 10));
    }
}
