@php
$workspace = currentWorkspace();
@endphp

@if($workspace && $workspace->hasCompletedOnboarding() && !$workspace->hasPaymentConfigured() && $workspace->isCreator())
    <div class="mb-6 p-4 bg-accent-50 border border-amber-200 rounded-lg">
        <div class="flex items-start">
            <flux:icon.exclamation-triangle class="w-6 h-6 bg-accent-500 mt-0.5 flex-shrink-0" />
            <div class="ml-3">
                <flux:heading size="sm" class="bg-accent-800">Payment Setup Required</flux:heading>
                <flux:text size="sm" class="bg-accent-700 mt-1">
                    You need to configure your payment account to start receiving payments from sponsors.
                </flux:text>
                <div class="mt-3">
                    <flux:button href="{{ route('settings.payments') }}" size="sm" variant="outline">
                        Set Up Payment Account
                    </flux:button>
                </div>
            </div>
        </div>
    </div>
@endif