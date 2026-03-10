@props([
    'booking',
    'acceptAction',
    'declineAction',
    'error' => null,
])

<div x-data="{ showDeclineModal: false }">
    <x-bookings.counter-offer-comparison :booking="$booking" />

    @if($booking->creator_notes)
        <blockquote class="mb-6 border-l-4 border-indigo-300 pl-4 italic text-zinc-600 dark:border-indigo-600 dark:text-zinc-400">
            "{{ $booking->creator_notes }}"
        </blockquote>
    @endif

    @if($error)
        <flux:callout variant="danger" icon="exclamation-triangle" class="mb-4">
            <flux:callout.text>{{ $error }}</flux:callout.text>
        </flux:callout>
    @endif

    <div class="flex flex-col gap-3 sm:flex-row">
        <flux:button
            wire:click="{{ $acceptAction }}"
            variant="primary"
            icon="check"
            class="flex-1"
        >
            Accept Counter-Offer
        </flux:button>
        <flux:button
            @click="showDeclineModal = true"
            variant="danger"
            icon="x-mark"
            class="flex-1"
        >
            Decline
        </flux:button>
    </div>

    <flux:modal x-model="showDeclineModal" class="md:w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Decline Counter-Offer?</flux:heading>
                <flux:text class="mt-2 text-zinc-500">
                    Are you sure you want to decline this counter-offer? The creator will be notified and the inquiry will be closed.
                </flux:text>
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:button variant="ghost" type="button" @click="showDeclineModal = false">
                    Cancel
                </flux:button>
                <flux:button
                    wire:click="{{ $declineAction }}"
                    @click="showDeclineModal = false"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-75"
                    variant="danger"
                    icon="x-mark"
                >
                    <span wire:loading.remove wire:target="{{ $declineAction }}">Yes, Decline</span>
                    <span wire:loading wire:target="{{ $declineAction }}">Declining…</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
