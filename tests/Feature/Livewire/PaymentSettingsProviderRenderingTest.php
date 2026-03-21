<?php

test('payment settings renders provider partial dynamically with includeIf fallback', function () {
    $component = file_get_contents(resource_path('views/pages/settings/⚡payment.blade.php'));

    expect($component)
        ->toContain("'livewire.onboarding.providers.'.\$this->provider")
        ->toContain('@includeIf($providerPartial)')
        ->toContain('Selected provider is not supported in settings yet.');
});
