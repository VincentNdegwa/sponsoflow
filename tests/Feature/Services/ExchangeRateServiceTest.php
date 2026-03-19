<?php

use App\Models\ExchangeRate;
use App\Services\ExchangeRateService;
use Illuminate\Support\Facades\Http;

test('it converts non usd amounts and stores daily exchange rate records', function () {
    Http::fake([
        'https://api.frankfurter.dev/v1/latest*' => Http::response([
            'amount' => 1,
            'base' => 'KES',
            'date' => now()->toDateString(),
            'rates' => [
                'USD' => 0.008,
            ],
        ]),
    ]);

    config()->set('currency.default_provider', 'frankfurter');

    $service = app(ExchangeRateService::class);
    $conversion = $service->convertToUsd(1000, 'KES');

    expect($conversion['amount_usd'])->toBe(8.00)
        ->and($conversion['exchange_rate_to_usd'])->toBe(0.008)
        ->and($conversion['provider'])->toBe('frankfurter');

    $hasRate = ExchangeRate::query()
        ->where('base_currency', 'KES')
        ->where('target_currency', 'USD')
        ->where('provider', 'frankfurter')
        ->whereDate('effective_date', now())
        ->exists();

    expect($hasRate)->toBeTrue();
});
