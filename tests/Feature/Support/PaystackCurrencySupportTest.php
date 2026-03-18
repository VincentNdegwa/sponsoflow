<?php

use App\Support\CurrencySupport;

test('it exposes only paystack supported countries for onboarding', function () {
    $countries = CurrencySupport::getPaystackSupportedCountries();

    expect(array_keys($countries))->toBe(['NG', 'GH', 'ZA', 'KE']);

    foreach ($countries as $country) {
        expect($country['providers'])->toContain('paystack');
    }
});

test('it exposes only paystack supported currencies for onboarding', function () {
    $currencies = CurrencySupport::getPaystackSupportedCurrencies();

    expect(array_keys($currencies))->toBe(CurrencySupport::PAYSTACK_SUPPORTED_CURRENCIES)
        ->and(array_keys($currencies))->not->toContain('USD')
        ->and(array_keys($currencies))->not->toContain('EUR')
        ->and(array_keys($currencies))->not->toContain('GBP')
        ->and(array_keys($currencies))->not->toContain('CAD');
});
