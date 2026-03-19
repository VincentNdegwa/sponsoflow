<?php

use App\Support\CurrencySupport;

return [
    'default_provider' => env('CURRENCY_RATE_PROVIDER'),

    'rate_cache_ttl_minutes' => (int) env('CURRENCY_RATE_CACHE_TTL_MINUTES', 1440),

    'supported_currencies' => array_values(array_unique([
        'USD',
        ...CurrencySupport::PAYSTACK_SUPPORTED_CURRENCIES,
    ])),

    'providers' => [
        'frankfurter' => [
            'base_url' => env('FRANKFURTER_BASE_URL', 'https://api.frankfurter.dev/v1'),
        ],

        'exchange_rate_api' => [
            'base_url' => env('EXCHANGE_RATE_API_BASE_URL', 'https://v6.exchangerate-api.com/v6'),
            'key' => env('EXCHANGE_RATE_API_KEY'),
        ],
    ],
];
