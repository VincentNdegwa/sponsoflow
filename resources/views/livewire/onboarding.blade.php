<?php

use App\Livewire\Concerns\HandlesPaystackPaymentSetup;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Attributes\Computed;

new #[Title('Creator Onboarding')] class extends Component {
    use HandlesPaystackPaymentSetup {
        createPaymentAccount as createPaystackPaymentAccount;
    }

    public array $availableProviders = [
        'stripe' => 'Stripe',
        'paystack' => 'Paystack',
    ];

    public bool $is_connecting = false;

    public function mount(): void
    {
        $this->provider = 'stripe';
    }

    public function updatedProvider(): void
    {
        if ($this->provider === 'paystack') {
            $this->initializePaystackPaymentSetup();

            return;
        }

        $this->is_connecting = false;
    }

    public function createPaymentAccount(): void
    {
        if ($this->provider === 'paystack') {
            $this->createPaystackPaymentAccount();

            return;
        }

        $this->connectStripeAccount();
    }

    public function skipPaymentSetup(): void
    {
        session()->flash('status', 'Setup completed! You can configure payment settings later.');
        session()->flash('info', 'Note: You will not receive payouts until payment setup is complete.');

        $this->completeOnboarding();
    }

    public function completeOnboarding(): void
    {
        try {
            $workspace = $this->getCurrentWorkspace();

            if ($workspace) {
                $workspace->completeOnboarding();
                $this->redirect('/dashboard');
            }
        } catch (\Throwable $exception) {
            Log::error('Failed to complete onboarding', [
                'user_id' => Auth::id(),
                'error' => $exception->getMessage(),
            ]);

            session()->flash('error', 'Failed to complete onboarding: '.$exception->getMessage());
        }
    }

    protected function afterPaymentAccountCreated(): void
    {
        $this->completeOnboarding();
    }

    protected function connectStripeAccount(): void
    {
        $this->is_connecting = true;

        try {
            $workspace = $this->getCurrentWorkspace();

            if (! $workspace) {
                throw new \Exception('No workspace found.');
            }

            $paymentService = app(PaymentService::class);
            $config = $workspace->paymentConfigurations()
                ->where('provider', 'stripe')
                ->first();
            $onboardingUrl = null;

            if ($config && $config->provider_account_id) {
                $onboardingUrl = $paymentService->getOnboardingUrl($config);
            } else {
                $response = $paymentService->createConnectAccount($workspace, 'stripe', []);
                $onboardingUrl = $response['onboarding_url'] ?? null;
            }

            if ($onboardingUrl) {
                $this->redirect($onboardingUrl, true);

                return;
            }

            session()->flash('status', 'Stripe account connected successfully.');
        } catch (\Throwable $exception) {
            Log::error('Failed to connect Stripe account', [
                'user_id' => Auth::id(),
                'provider' => 'stripe',
                'error' => $exception->getMessage(),
            ]);

            session()->flash('error', $this->resolveStripeErrorMessage($exception->getMessage()));
        } finally {
            $this->is_connecting = false;
        }
    }

    protected function resolveStripeErrorMessage(string $message): string
    {
        $lowerMessage = strtolower($message);

        if (str_contains($lowerMessage, 'signed up for connect')) {
            return 'Stripe Connect is not enabled for this account yet. Visit https://dashboard.stripe.com/connect to enable Connect, then try again.';
        }

        return 'Failed to connect Stripe account: '.$message;
    }

    #[Computed]
    public function isStripeVerified(): bool
    {
        $workspace = $this->getCurrentWorkspace();

        if (! $workspace) {
            return false;
        }

        $config = $workspace->paymentConfigurations()
            ->where('provider', 'stripe')
            ->first();

        return (bool) ($config && $config->is_verified);
    }
}; ?>

<div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/50 backdrop-blur-xs">
    <div class="max-h-[90vh] w-full max-w-lg overflow-hidden rounded-lg bg-gray-100 shadow-xl dark:bg-zinc-950">
        <div class="px-6 py-4">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="lg">Welcome!</flux:heading>
                    <flux:text size="sm" class="text-accent-100">Set up your creator workspace</flux:text>
                </div>
                <div class="rounded bg-white/20 px-2 py-1 text-xs">
                    {{ ucfirst($this->provider) }}
                </div>
            </div>
        </div>

        <div class="max-h-[70vh] overflow-y-auto p-6">
            <div class="mb-6">
                <flux:select wire:model.live="provider" label="Payment Provider" placeholder="Choose a provider" required>
                    @foreach ($availableProviders as $key => $label)
                        <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            @if ($this->provider === 'stripe')
                @include('livewire.onboarding.providers.stripe')
            @else
                @include('livewire.onboarding.providers.paystack')
            @endif
        </div>

        @if (session('error'))
            <div class="mx-6 mb-6 rounded-md border border-red-200 bg-red-50 p-3">
                <flux:text class="text-red-800">{{ session('error') }}</flux:text>
            </div>
        @endif

        @if (session('status'))
            <div class="mx-6 mb-6 rounded-md border border-primary-200 bg-primary-50 p-3">
                <flux:text class="text-primary-800">{{ session('status') }}</flux:text>
            </div>
        @endif

        @if (session('info'))
            <div class="mx-6 mb-6 rounded-md border border-amber-200 bg-accent-50 p-3">
                <flux:text class="bg-accent-700">{{ session('info') }}</flux:text>
            </div>
        @endif
    </div>
</div>
