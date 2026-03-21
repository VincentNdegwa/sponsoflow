<?php

use App\Livewire\Concerns\HandlesPaystackPaymentSetup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Creator Onboarding')] class extends Component {
    use HandlesPaystackPaymentSetup;

    public function mount(): void
    {
        $this->initializePaystackPaymentSetup();
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
            @if($this->provider === 'paystack')
                @include('livewire.onboarding.providers.paystack')
            @else
                <div class="rounded-md border border-amber-200 bg-amber-50 p-4">
                    <flux:text class="text-amber-700">Selected provider is not supported in onboarding yet.</flux:text>
                </div>
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
            <div class="mx-6 mb-6 rounded-md border border-amber-200 bg-amber-50 p-3">
                <flux:text class="text-amber-700">{{ session('info') }}</flux:text>
            </div>
        @endif
    </div>
</div>
