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

        try {
            $response = Http::timeout($this->timeout())
                ->get($this->baseUrl().'/gold-price-india', [
                    'access_key' => $this->apiKey(),
                    'symbols' => $symbol,
                ]);

            if (! $response->successful() || ! $response->json('success')) {
                Log::warning('Metals-API gold-price-india failed', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                return null;
            }

            $rate = data_get($response->json(), "rates.{$symbol}");

            if (! is_numeric($rate)) {
                Log::warning('Metals-API gold symbol missing from response', ['symbol' => $symbol]);

                return null;
            }

            return round((float) $rate, 2);
        } catch (\Throwable $exception) {
            Log::warning('Metals-API gold request exception', ['message' => $exception->getMessage()]);

            return null;
        }
    }

    public function fetchSilverRatePerGram(): ?float
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::timeout($this->timeout())
                ->get($this->baseUrl().'/convert', [
                    'access_key' => $this->apiKey(),
                    'from' => 'XAG',
                    'to' => $this->currency(),
                    'amount' => 1,
                ]);

            if (! $response->successful() || ! $response->json('success')) {
                return $this->fetchSilverFromLatest();
            }

            $inrPerTroyOz = data_get($response->json(), 'result');

            if (! is_numeric($inrPerTroyOz)) {
                return $this->fetchSilverFromLatest();
            }

            return round((float) $inrPerTroyOz / self::TROY_OUNCE_GRAMS, 2);
        } catch (\Throwable $exception) {
            Log::warning('Metals-API silver convert exception', ['message' => $exception->getMessage()]);

            return $this->fetchSilverFromLatest();
        }
    }

    protected function fetchSilverFromLatest(): ?float
    {
        try {
            $response = Http::timeout($this->timeout())
                ->get($this->baseUrl().'/latest', [
                    'access_key' => $this->apiKey(),
                    'base' => 'USD',
                    'symbols' => 'USDXAG,'.$this->currency(),
                ]);

            if (! $response->successful() || ! $response->json('success')) {
                Log::warning('Metals-API silver latest failed', ['status' => $response->status()]);

                return null;
            }

            $rates = $response->json('rates', []);
            $usdPerOz = $rates['USDXAG'] ?? null;
            $currencyRate = $rates[$this->currency()] ?? null;

            if (! is_numeric($usdPerOz) || ! is_numeric($currencyRate) || (float) $currencyRate <= 0) {
                return null;
            }

            // Base USD: USDXAG = USD/troy oz, INR = INR per 1 USD.
            $inrPerOz = (float) $usdPerOz * (float) $currencyRate;

            return round($inrPerOz / self::TROY_OUNCE_GRAMS, 2);
        } catch (\Throwable $exception) {
            Log::warning('Metals-API silver latest exception', ['message' => $exception->getMessage()]);

            return null;
        }
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
