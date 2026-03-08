<?php

namespace App\Support;

class CurrencySupport
{
    /**
     * Supported currencies with their details
     */
    public static function getSupportedCurrencies(): array
    {
        return [
            'USD' => [
                'name' => 'US Dollar',
                'symbol' => '$',
                'decimal_places' => 2,
                'countries' => ['US', 'global'],
            ],
            'EUR' => [
                'name' => 'Euro',
                'symbol' => '€',
                'decimal_places' => 2,
                'countries' => ['EU', 'DE', 'FR', 'IT', 'ES', 'NL', 'AT', 'BE'],
            ],
            'GBP' => [
                'name' => 'British Pound',
                'symbol' => '£',
                'decimal_places' => 2,
                'countries' => ['GB', 'UK'],
            ],
            'NGN' => [
                'name' => 'Nigerian Naira',
                'symbol' => '₦',
                'decimal_places' => 2,
                'countries' => ['NG'],
            ],
            'GHS' => [
                'name' => 'Ghanaian Cedi',
                'symbol' => '₵',
                'decimal_places' => 2,
                'countries' => ['GH'],
            ],
            'ZAR' => [
                'name' => 'South African Rand',
                'symbol' => 'R',
                'decimal_places' => 2,
                'countries' => ['ZA'],
            ],
            'KES' => [
                'name' => 'Kenyan Shilling',
                'symbol' => 'KSh',
                'decimal_places' => 2,
                'countries' => ['KE'],
            ],
            'CAD' => [
                'name' => 'Canadian Dollar',
                'symbol' => 'C$',
                'decimal_places' => 2,
                'countries' => ['CA'],
            ],
        ];
    }

    /**
     * Get supported countries with their details
     */
    public static function getSupportedCountries(): array
    {
        return [
            'US' => [
                'name' => 'United States',
                'currency' => 'USD',
                'providers' => ['stripe'],
                'timezone' => 'America/New_York',
            ],
            'CA' => [
                'name' => 'Canada',
                'currency' => 'CAD',
                'providers' => ['stripe'],
                'timezone' => 'America/Toronto',
            ],
            'GB' => [
                'name' => 'United Kingdom',
                'currency' => 'GBP',
                'providers' => ['stripe'],
                'timezone' => 'Europe/London',
            ],
            'NG' => [
                'name' => 'Nigeria',
                'currency' => 'NGN',
                'providers' => ['paystack', 'stripe'],
                'timezone' => 'Africa/Lagos',
            ],
            'GH' => [
                'name' => 'Ghana',
                'currency' => 'GHS',
                'providers' => ['paystack'],
                'timezone' => 'Africa/Accra',
            ],
            'ZA' => [
                'name' => 'South Africa',
                'currency' => 'ZAR',
                'providers' => ['paystack', 'stripe'],
                'timezone' => 'Africa/Johannesburg',
            ],
            'KE' => [
                'name' => 'Kenya',
                'currency' => 'KES',
                'providers' => ['paystack'],
                'timezone' => 'Africa/Nairobi',
            ],
            'DE' => [
                'name' => 'Germany',
                'currency' => 'EUR',
                'providers' => ['stripe'],
                'timezone' => 'Europe/Berlin',
            ],
            'FR' => [
                'name' => 'France',
                'currency' => 'EUR',
                'providers' => ['stripe'],
                'timezone' => 'Europe/Paris',
            ],
        ];
    }

    /**
     * Get the recommended provider based on creator and brand countries
     */
    public static function getRecommendedProvider(string $creatorCountry, string $brandCountry = 'global'): string
    {
        $countries = self::getSupportedCountries();
        
        $africanCountries = ['NG', 'GH', 'ZA', 'KE'];
        $westernCountries = ['US', 'CA', 'GB', 'DE', 'FR'];
        
        if (in_array($creatorCountry, $africanCountries)) {
            return 'paystack';
        }
        
        if (in_array($creatorCountry, $westernCountries)) {
            return 'stripe';
        }
        
        if (isset($countries[$creatorCountry])) {
            $providers = $countries[$creatorCountry]['providers'];
            return $providers[0]; // Return first preferred provider
        }
        
        return 'stripe';
    }

    /**
     * Format currency amount
     */
    public static function formatCurrency(float $amount, string $currency, string $locale = 'en_US'): string
    {
        $currencies = self::getSupportedCurrencies();
        
        if (!isset($currencies[$currency])) {
            return number_format($amount, 2);
        }
        
        $currencyData = $currencies[$currency];
        $symbol = $currencyData['symbol'];
        $decimals = $currencyData['decimal_places'];
        
        // Format based on currency
        switch ($currency) {
            case 'NGN':
            case 'GHS':
            case 'ZAR':
            case 'KES':
                return $symbol . number_format($amount, $decimals);
            case 'EUR':
                return number_format($amount, $decimals) . ' ' . $symbol;
            default:
                return $symbol . number_format($amount, $decimals);
        }
    }

    /**
     * Convert currency to smallest unit (cents, kobo, etc.)
     */
    public static function toSmallestUnit(float $amount, string $currency): int
    {
        $currencies = self::getSupportedCurrencies();
        $decimals = $currencies[$currency]['decimal_places'] ?? 2;
        return (int) ($amount * (10 ** $decimals));
    }

    /**
     * Convert from smallest unit back to major currency unit
     */
    public static function fromSmallestUnit(int $amount, string $currency): float
    {
        $currencies = self::getSupportedCurrencies();
        $decimals = $currencies[$currency]['decimal_places'] ?? 2;
        return $amount / (10 ** $decimals);
    }

    /**
     * Get default currency for a country
     */
    public static function getDefaultCurrency(string $countryCode): string
    {
        $countries = self::getSupportedCountries();
        return $countries[$countryCode]['currency'] ?? 'USD';
    }

    /**
     * Get available providers for a country
     */
    public static function getAvailableProviders(string $countryCode): array
    {
        $countries = self::getSupportedCountries();
        return $countries[$countryCode]['providers'] ?? ['stripe'];
    }

    /**
     * Check if a currency is supported by a provider
     */
    public static function isCurrencySupportedByProvider(string $currency, string $provider): bool
    {
        $supportedCurrencies = [
            'stripe' => ['USD', 'EUR', 'GBP', 'CAD', 'NGN', 'GHS', 'ZAR', 'KES'],
            'paystack' => ['NGN', 'GHS', 'ZAR', 'KES'],
        ];

        return in_array($currency, $supportedCurrencies[$provider] ?? []);
    }
}