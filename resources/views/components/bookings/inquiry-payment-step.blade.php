@props([
    'booking',
    'purpose' => 'respond',
    'action',
    'error' => null,
    'backAction' => null,
    'backLabel' => '← Back',
])

<div class="rounded-lg border border-indigo-200 bg-white p-6 dark:border-indigo-700 dark:bg-zinc-800">
    <flux:heading size="lg" class="mb-1">
        @if($purpose === 'accept_counter')
            Complete Your Booking — Counter-Offer Accepted
        @else
            Complete Your Booking
        @endif
    </flux:heading>

    <flux:text class="mb-6 text-zinc-600 dark:text-zinc-400">
        @if($purpose === 'accept_counter')
            You're accepting the counter-offer of
            <strong class="text-zinc-700 dark:text-zinc-200">{{ $booking->formatAmount((float) $booking->counter_amount) }}</strong>.
            Fill in the campaign details below to proceed to payment.
        @else
            Your inquiry was approved at
            <strong class="text-zinc-700 dark:text-zinc-200">{{ $booking->formatAmount() }}</strong>.
            Fill in the campaign details below to proceed to payment.
        @endif
    </flux:text>

    <x-bookings.checkout-form
        :requirements="$booking->product->requirements"
        :action="$action"
        :error="$error"
        :back-action="$backAction"
        :back-label="$backLabel"
    />
</div>
