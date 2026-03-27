<div class="space-y-6">
    <div class="text-center">
        <flux:icon.sparkles class="mx-auto mb-2 h-12 w-12 text-primary-600" />
        <flux:heading size="lg">Connect Stripe</flux:heading>
        <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">
            Stripe handles payouts securely. Connect your account to start receiving payments.
        </flux:text>
    </div>

    <div class="rounded-md border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex items-start gap-3">
            <flux:icon.lock-closed class="h-5 w-5 text-zinc-500" />
            <div>
                <flux:text class="font-medium text-zinc-800 dark:text-zinc-100">Secure onboarding</flux:text>
                <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">
                    You will be redirected to Stripe to complete payout setup.
                </flux:text>
            </div>
        </div>
    </div>

    <div class="flex items-center justify-between">
        <div>
            @if ($this->hasExistingPaymentAccount)
                <flux:badge color="blue" size="sm">Stripe account linked</flux:badge>
            @else
                <flux:badge color="zinc" size="sm">Stripe account not linked</flux:badge>
            @endif
        </div>
        <flux:button wire:click="createPaymentAccount" variant="primary" :disabled="$is_connecting" class="min-w-40">
            @if ($is_connecting)
                <flux:icon.arrow-path class="h-4 w-4 animate-spin" />
                {{ $this->paymentAccountSubmittingLabel }}
            @else
                {{ $this->paymentAccountSubmitLabel }}
            @endif
        </flux:button>
    </div>

    @if ($this->isStripeVerified)
        <div class="rounded-md border border-green-200 bg-green-50 p-3">
            <div class="flex items-center gap-2">
                <flux:icon.check-circle class="h-5 w-5 text-green-500" />
                <flux:text class="text-green-800">Your Stripe account is verified and ready.</flux:text>
            </div>
        </div>
    @endif
</div>

