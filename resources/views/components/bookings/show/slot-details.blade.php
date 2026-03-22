@props(['booking'])

<div {{ $attributes->merge(['class' => 'rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800']) }}>
    <div class="mb-4 flex items-center gap-3">
        <div class="rounded-lg bg-zinc-100 p-2 dark:bg-zinc-900">
            <flux:icon icon="calendar-days" class="size-5 text-accent-content" />
        </div>
        <div>
            <flux:heading size="lg">Slot Details</flux:heading>
            <flux:text class="text-xs text-zinc-500">Schedule and delivery window</flux:text>
        </div>
    </div>

    @if(! $booking->slot)
        <flux:text class="text-sm text-zinc-500">This booking does not use a fixed slot.</flux:text>
    @else
        <div class="space-y-3">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">Date</flux:text>
                    <flux:text class="mt-1">{{ formatWorkspaceDate($booking->slot->slot_date) }}</flux:text>
                </div>
                <div>
                    <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">Time</flux:text>
                    <flux:text class="mt-1">{{ formatWorkspaceTime($booking->slot->slot_date) }}</flux:text>
                </div>
                <div>
                    <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">Slot Price</flux:text>
                    <flux:text class="mt-1">
                        {{ \App\Support\CurrencySupport::formatCurrency((float) $booking->slot->price, $booking->currency ?? 'USD') }}
                    </flux:text>
                </div>
                <div>
                    <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">Status</flux:text>
                    <div class="mt-1">
                        <flux:badge :color="$booking->slot->status->badgeColor()">{{ $booking->slot->status->label() }}</flux:badge>
                    </div>
                </div>
            </div>

            @if($booking->slot->notes)
                <div class="rounded-md bg-zinc-50 p-3 dark:bg-zinc-900">
                    <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">Slot Notes</flux:text>
                    <flux:text class="mt-1 text-sm">{{ $booking->slot->notes }}</flux:text>
                </div>
            @endif
        </div>
    @endif
</div>
