@props(['activitySummary'])

<div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
    <div class="mb-5 flex items-center justify-between">
        <div>
            <flux:heading size="sm">Booking Activity</flux:heading>
            <flux:text class="text-xs text-zinc-400">Last 30 days summary</flux:text>
        </div>
    </div>
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-xs text-zinc-500">Recent</flux:text>
            <flux:heading size="lg">{{ $activitySummary['recent'] }}</flux:heading>
        </div>
        <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-xs text-zinc-500">Active</flux:text>
            <flux:heading size="lg">{{ $activitySummary['active'] }}</flux:heading>
        </div>
        <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-xs text-zinc-500">Pending</flux:text>
            <flux:heading size="lg">{{ $activitySummary['pending'] }}</flux:heading>
        </div>
    </div>
</div>

