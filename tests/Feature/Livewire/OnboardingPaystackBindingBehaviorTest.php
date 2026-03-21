<?php

test('paystack onboarding only live-updates dependency-driving selects', function () {
    $view = file_get_contents(resource_path('views/livewire/onboarding/providers/paystack.blade.php'));

    expect($view)
        ->toContain('wire:model.change.live="country_code"')
        ->toContain('wire:model.change.live="currency"')
        ->toContain('wire:model.change.live="payment_method"')
        ->toContain('wire:model="bank_code"')
        ->toContain('wire:model="account_number"')
        ->toContain('wire:model="account_name"')
        ->not->toContain('wire:model.live="bank_code"')
        ->not->toContain('wire:model.live="account_number"')
        ->not->toContain('wire:model.live="account_name"')
        ->not->toContain('wire:model.blur="account_number"')
        ->not->toContain('wire:model.blur="account_name"');
});

test('onboarding component does not log payment method config on each render', function () {
    $component = file_get_contents(resource_path('views/livewire/onboarding.blade.php'));

    expect($component)->not->toContain('Log::info($this->selectedPaymentMethodConfig)');
});

test('onboarding uses country currency list for allowed currencies and supported_currencies for methods', function () {
    $component = file_get_contents(app_path('Livewire/Concerns/HandlesPaystackPaymentSetup.php'));

    expect($component)
        ->toContain('relationships.currency.data')
        ->toContain('relationships.currency.supported_currencies');
});
