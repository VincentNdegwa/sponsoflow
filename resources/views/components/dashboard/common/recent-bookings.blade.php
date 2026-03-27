@props(['recentBookings', 'isCreator', 'isCreatorUsdView', 'workspace'])

<div class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
    <div class="flex items-center justify-between border-b border-zinc-100 px-6 py-4 dark:border-zinc-800">
        <flux:heading size="sm">Recent Bookings</flux:heading>
        <flux:button variant="ghost" size="sm" :href="route('bookings.index')" wire:navigate>
            View all
        </flux:button>
    </div>

    @if ($recentBookings->isEmpty())
        <div class="flex flex-col items-center justify-center py-16 text-center">
            <flux:icon icon="calendar-days" class="mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
            <flux:text class="font-medium text-zinc-600 dark:text-zinc-400">No bookings yet</flux:text>
            <flux:text class="mt-1 text-sm text-zinc-400 dark:text-zinc-500">
                @if ($isCreator)
                    Create a product to start receiving bookings.
                @else
                    Browse creators and make your first booking.
                @endif
            </flux:text>
        </div>
    @else
        <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
            @foreach ($recentBookings as $booking)
                <a href="{{ route('bookings.show', $booking) }}" wire:navigate
                    class="flex items-center justify-between px-6 py-4 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-sm font-semibold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                            @if ($isCreator)
                                {{ strtoupper(substr($booking->guest_name ?? ($booking->brandUser?->name ?? '?'), 0, 1)) }}
                            @else
                                {{ strtoupper(substr($booking->product?->name ?? '?', 0, 1)) }}
                            @endif
                        </div>
                        <div class="min-w-0">
                            <div class="truncate text-sm font-medium text-zinc-900 dark:text-white">
                                @if ($isCreator)
                                    {{ $booking->guest_name ?? ($booking->brandUser?->name ?? ($booking->guest_email ?? 'Guest')) }}
                                @else
                                    {{ $booking->product?->name ?? 'Booking' }}
                                @endif
                            </div>
                            <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-500">
                                {{ $booking->created_at->diffForHumans() }}
                            </div>
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-3">
                        <flux:badge :color="$booking->status->badgeColor()" size="sm">
                            {{ $booking->status->label() }}
                        </flux:badge>
                        <span class="w-20 text-right text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            @if ($isCreator)
                                @if ($isCreatorUsdView)
                                    {{ formatMoney((float) ($booking->latestPayment?->amount_usd ?? 0), $workspace, 'USD') }}
                                @else
                                    {{ $booking->formatAmount((float) ($booking->latestPayment?->amount ?? $booking->amount_paid)) }}
                                @endif
                            @else
                                {{ formatMoney((float) ($booking->latestPayment?->amount_usd ?? 0), $workspace, 'USD') }}
                            @endif
                        </span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>

