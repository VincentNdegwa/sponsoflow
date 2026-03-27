<?php

use App\Livewire\Concerns\HandlesStripePaymentSetup;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Payment settings')] class extends Component {
    use HandlesStripePaymentSetup;

    public bool $showForm = true;

    public function mount(): void
    {
        $this->initializeStripePaymentSetup();
        $this->showForm = ! $this->storedPaymentConfiguration;
        $this->bypassExistingPaymentAccount = $this->showForm;
    }

    public function startChangingAccount(): void
    {
        $this->showForm = true;
        $this->bypassExistingPaymentAccount = true;
        $this->resetAccountFields();
    }

    public function cancelChangingAccount(): void
    {
        $this->showForm = false;
        $this->bypassExistingPaymentAccount = false;
        $this->resetAccountFields();
    }

    public function completeOnboarding(): void
    {
        $this->redirect('/dashboard');
    }

    protected function afterPaymentAccountCreated(): void
    {
        $this->showForm = true;
        $this->bypassExistingPaymentAccount = false;
    }

    #[Computed]
    public function recommendedProvider(): string
    {
        return $this->provider;
    }

    #[Computed]
    public function workspaceCurrency(): string
    {
        $workspace = $this->getCurrentWorkspace();
        return $workspace ? $workspace->currency : 'USD';
    }

    #[Computed]
    public function workspaceCountry(): string
    {
        $workspace = $this->getCurrentWorkspace();
        return $workspace ? $workspace->country_code : 'KE';
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Payment settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Payment Account')" :subheading="__('Configure your payment account to receive funds')">
        @if($this->storedPaymentConfiguration && !$this->showForm)
            <div class="space-y-6">
                <flux:card class="p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="lg">{{ ucfirst($this->recommendedProvider) }} Account</flux:heading>
                            <flux:subheading class="mt-1">
                                Status:
                                @if($this->isStripeVerified)
                                    <flux:badge color="green" size="sm">Verified & Active</flux:badge>
                                @else
                                    <flux:badge color="amber" size="sm">Pending Verification</flux:badge>
                                @endif
                            </flux:subheading>
                        </div>
                        <div class="text-right">
                            <flux:text size="sm" class="text-gray-500">
                                Currency: {{ $this->workspaceCurrency }}
                            </flux:text>
                            <flux:text size="sm" class="text-gray-500">
                                Account ID: {{ $this->storedPaymentConfiguration->provider_account_id }}
                            </flux:text>
                        </div>
                    </div>

                    @if($this->isStripeVerified)
                        <div class="mt-4 p-4 bg-green-50 border border-green-200 rounded-md">
                            <div class="flex">
                                <flux:icon.check-circle class="w-5 h-5 text-green-400 mt-0.5" />
                                <div class="ml-3">
                                    <flux:text class="text-green-800 font-medium">
                                        Your payment account is active and ready to receive funds!
                                    </flux:text>
                                    <flux:text size="sm" class="text-green-600 mt-1">
                                        You can now receive payments for your sponsorship slots.
                                    </flux:text>
                                </div>
                            </div>
                        </div>
                    @endif
                </flux:card>

                <flux:button wire:click="startChangingAccount" variant="filled" class="w-full">
                    {{ __('Change Payment Account') }}
                </flux:button>
            </div>
        @else
            <div class="space-y-6">
                @php($providerPartial = 'livewire.onboarding.providers.'.$this->provider)

                @includeIf($providerPartial)

                @if(! view()->exists($providerPartial))
                    <div class="rounded-md border border-amber-200 bg-accent-50 p-4">
                        <flux:text class="bg-accent-700">Selected provider is not supported in settings yet.</flux:text>
                    </div>
                @endif

                {{-- Security Notice --}}
                <div class="mt-6 p-4 bg-gray-50 border border-gray-200 rounded-md">
                    <div class="flex">
                        <flux:icon.shield-check class="w-5 h-5 text-gray-400 mt-0.5" />
                        <div class="ml-3">
                            <flux:text class="text-gray-800 font-medium text-sm">
                                Your security is important to us
                            </flux:text>
                            <flux:text size="sm" class="text-gray-600 mt-1">
                                Stripe collects your payout details securely during onboarding. We only save the account reference that Stripe returns to us.
                            </flux:text>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Flash Messages --}}
        @if (session('status'))
            <div class="mt-4 p-4 bg-green-50 border border-green-200 rounded-md">
                <flux:text class="text-green-800">{{ session('status') }}</flux:text>
            </div>
        @endif

        @if (session('error'))
            <div class="mt-4 p-4 bg-red-50 border border-red-200 rounded-md">
                <flux:text class="text-red-800">{{ session('error') }}</flux:text>
            </div>
        @endif
    </x-pages::settings.layout>
</section>
