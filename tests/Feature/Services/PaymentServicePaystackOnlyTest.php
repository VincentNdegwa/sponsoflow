<?php

use App\Models\Workspace;
use App\Services\PaymentService;

test('it exposes only paystack as available provider', function () {
    $service = app(PaymentService::class);

    expect($service->getAvailableProviders())->toBe(['paystack']);
});

test('it rejects creating connect account with non paystack provider', function () {
    $workspace = Workspace::factory()->create([
        'country_code' => 'US',
        'currency' => 'USD',
    ]);

    $service = app(PaymentService::class);

    expect(fn () => $service->createConnectAccount($workspace, 'stripe', []))
        ->toThrow("Provider 'stripe' is currently disabled. Active provider: paystack");
});

test('it rejects fetching banks with non paystack provider', function () {
    $service = app(PaymentService::class);

    expect(fn () => $service->getSupportedBanks('stripe', 'NG'))
        ->toThrow("Provider 'stripe' is currently disabled. Active provider: paystack");
});

test('it rejects bank verification with non paystack provider', function () {
    $service = app(PaymentService::class);

    expect(fn () => $service->verifyBankAccount('0000000000', '000', 'stripe'))
        ->toThrow("Provider 'stripe' is currently disabled. Active provider: paystack");
});
