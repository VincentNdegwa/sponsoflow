@props(['booking'])

<div class="mb-6 grid gap-4 sm:grid-cols-2">
    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
        <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-400">Your original offer</flux:text>
        <p class="mt-1 text-2xl font-bold text-zinc-400 line-through">{{ $booking->formatAmount() }}</p>
    </div>
    <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-4 dark:border-indigo-700 dark:bg-indigo-950">
        <flux:text class="text-xs font-medium uppercase tracking-wide text-indigo-600 dark:text-indigo-400">Counter-offer</flux:text>
        <p class="mt-1 text-2xl font-bold text-indigo-700 dark:text-indigo-300">{{ $booking->formatAmount((float) $booking->counter_amount) }}</p>
    </div>
</div>
