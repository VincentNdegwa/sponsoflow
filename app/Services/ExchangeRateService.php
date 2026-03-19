<?php

namespace App\Services;

use App\Models\ExchangeRate;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ExchangeRateService
{
    public function convertToUsd(float $amount, string $fromCurrency): array
    {
        $normalizedCurrency = strtoupper($fromCurrency);

        if ($normalizedCurrency === 'USD') {
            return [
                'amount_usd' => round($amount, 2),
                'exchange_rate_to_usd' => 1.0,
                'provider' => 'identity',
                'fetched_at' => now(),
            ];
        }

        $rateData = $this->getRateToUsd($normalizedCurrency);

        return [
            'amount_usd' => round($amount * $rateData['rate'], 2),
            'exchange_rate_to_usd' => $rateData['rate'],
            'provider' => $rateData['provider'],
            'fetched_at' => $rateData['fetched_at'],
        ];
    }

    public function getRateToUsd(string $fromCurrency, bool $forceRefresh = false): array
    {
        $baseCurrency = strtoupper($fromCurrency);
        $targetCurrency = 'USD';
        $today = now()->toDateString();
        $provider = $this->resolveProvider();

        if ($baseCurrency === $targetCurrency) {
            return [
                'rate' => 1.0,
                'provider' => 'identity',
                'fetched_at' => now(),
            ];
        }

        $cacheKey = $this->cacheKey($baseCurrency, $targetCurrency, $today);

        if (! $forceRefresh) {
            $cachedRate = Cache::get($cacheKey);
            if ($cachedRate !== null) {
                return $cachedRate;
            }

            $storedRate = ExchangeRate::query()
                ->where('base_currency', $baseCurrency)
                ->where('target_currency', $targetCurrency)
                ->where('provider', $provider)
                ->where('effective_date', $today)
                ->latest('fetched_at')
                ->first();

            if ($storedRate) {
                $data = [
                    'rate' => (float) $storedRate->rate,
                    'provider' => $storedRate->provider,
                    'fetched_at' => $storedRate->fetched_at,
                ];

                Cache::put($cacheKey, $data, $this->cacheTtlSeconds());

                return $data;
            }
        }

        $rateData = $this->fetchFromConfiguredProvider($provider, $baseCurrency, $targetCurrency);

        ExchangeRate::updateOrCreate(
            [
                'base_currency' => $baseCurrency,
                'target_currency' => $targetCurrency,
                'provider' => $rateData['provider'],
                'effective_date' => $today,
            ],
            [
                'rate' => $rateData['rate'],
                'fetched_at' => $rateData['fetched_at'],
            ]
        );

        Cache::put($cacheKey, $rateData, $this->cacheTtlSeconds());

        return $rateData;
    }

    public function syncSupportedRates(?string $preferredProvider = null): array
    {
        $provider = $this->resolveProvider();

        if ($preferredProvider !== null && $preferredProvider !== $provider) {
            throw new \RuntimeException("Preferred provider '{$preferredProvider}' does not match configured provider '{$provider}'.");
        }

        $currencies = config('currency.supported_currencies', ['USD']);
        $results = [];

        foreach ($currencies as $currency) {
            $baseCurrency = strtoupper($currency);

            if ($baseCurrency === 'USD') {
                continue;
            }

            $results[$baseCurrency] = $this->getRateToUsd(
                $baseCurrency,
                forceRefresh: true
            );
        }

        return $results;
    }

    private function fetchFromConfiguredProvider(string $provider, string $baseCurrency, string $targetCurrency): array
    {
        $rate = match ($provider) {
            'frankfurter' => $this->fetchFromFrankfurter($baseCurrency, $targetCurrency),
            'exchange_rate_api' => $this->fetchFromExchangeRateApi($baseCurrency, $targetCurrency),
            default => throw new \RuntimeException("Unsupported currency provider '{$provider}'."),
        };

        if ($rate === null || $rate <= 0) {
            throw new \RuntimeException("Invalid rate received from provider '{$provider}' for {$baseCurrency}->{$targetCurrency}.");
        }

        return [
            'rate' => $rate,
            'provider' => $provider,
            'fetched_at' => now(),
        ];
    }

    private function fetchFromFrankfurter(string $baseCurrency, string $targetCurrency): ?float
    {
        $url = config('currency.providers.frankfurter.base_url', 'https://api.frankfurter.dev/v1');

        $response = Http::timeout(10)
            ->get(rtrim($url, '/').'/latest', [
                'base' => $baseCurrency,
                'symbols' => $targetCurrency,
            ]);

        if (! $response->successful()) {
            $message = Arr::get($response->json(), 'message', $response->body());
            throw new \RuntimeException("Frankfurter request failed for {$baseCurrency}->{$targetCurrency}: {$message}");
        }

        $rate = Arr::get($response->json(), "rates.{$targetCurrency}");

        if ($rate === null) {
            throw new \RuntimeException("Frankfurter has no {$targetCurrency} rate for base {$baseCurrency}.");
        }

        return (float) $rate;
    }

    private function fetchFromExchangeRateApi(string $baseCurrency, string $targetCurrency): ?float
    {
        $key = config('currency.providers.exchange_rate_api.key');

        if (empty($key)) {
            throw new \RuntimeException('ExchangeRate-API key not configured');
        }

        $url = config('currency.providers.exchange_rate_api.base_url', 'https://v6.exchangerate-api.com/v6');

        $response = Http::timeout(10)
            ->get(rtrim($url, '/').'/'.$key.'/latest/'.$baseCurrency);

        if (! $response->successful()) {
            throw new \RuntimeException('ExchangeRate-API request failed: '.$response->body());
        }

        return (float) Arr::get($response->json(), "conversion_rates.{$targetCurrency}");
    }

    private function resolveProvider(): string
    {
        $configuredProvider = config('currency.default_provider');

        if (! is_string($configuredProvider) || trim($configuredProvider) === '') {
            throw new \RuntimeException('Currency provider is not configured. Set CURRENCY_RATE_PROVIDER to frankfurter or exchange_rate_api.');
        }

        $provider = trim($configuredProvider);

        if (! in_array($provider, ['frankfurter', 'exchange_rate_api'], true)) {
            throw new \RuntimeException("Unsupported configured currency provider '{$provider}'.");
        }

        return $provider;
    }

    private function cacheTtlSeconds(): int
    {
        $minutes = (int) config('currency.rate_cache_ttl_minutes', 1440);

        return max(60, $minutes * 60);
    }

    private function cacheKey(string $baseCurrency, string $targetCurrency, string $date): string
    {
        return "exchange-rate:{$baseCurrency}:{$targetCurrency}:{$date}";
    }
}
