@props(['stats'])

<div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
    @foreach ($stats as $stat)
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="mb-4 flex items-center justify-between">
                <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $stat['label'] }}</span>
                <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                    <flux:icon :icon="$stat['icon']" class="size-4 text-zinc-500 dark:text-zinc-400" />
                </div>
            </div>
            <div class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">
                {{ $stat['value'] }}
            </div>
            <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-500">{{ $stat['sub'] }}</div>
        </div>
    @endforeach
</div>

