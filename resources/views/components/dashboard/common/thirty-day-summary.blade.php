@props(['summary', 'isCreator', 'isCreatorUsdView', 'workspace'])

<div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
    <div class="mb-5 flex items-center justify-between">
        <div>
            <flux:heading size="sm">30-Day Summary</flux:heading>
            <flux:text class="text-xs text-zinc-400">Bookings and payments</flux:text>
        </div>
    </div>
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-xs text-zinc-500">Bookings</flux:text>
            <flux:heading size="lg">{{ $summary['bookings'] }}</flux:heading>
        </div>
        <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-xs text-zinc-500">Completed Payments</flux:text>
            <flux:heading size="lg">{{ $summary['payments'] }}</flux:heading>
        </div>
        <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-xs text-zinc-500">Total Volume</flux:text>
            <flux:heading size="lg">
                @if ($isCreator)
                    @if ($isCreatorUsdView)
                        {{ formatMoney($summary['total'], $workspace, 'USD') }}
                    @else
                        {{ $workspace?->formatCurrency($summary['total']) ?? formatMoney($summary['total'], null, 'USD') }}
                    @endif
                @else
                    {{ formatMoney($summary['total'], $workspace, 'USD') }}
                @endif
            </flux:heading>
        </div>
    </div>
</div>

