@props(['avgRating', 'ratingCount'])

@if ($ratingCount > 0)
    <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
        <flux:heading size="sm" class="mb-1">Your Rating</flux:heading>
        <flux:text class="mb-4 text-xs text-zinc-400">Based on {{ $ratingCount }} review{{ $ratingCount !== 1 ? 's' : '' }}</flux:text>
        <div class="flex items-end gap-2">
            <span class="text-4xl font-semibold tracking-tight text-zinc-900 dark:text-white">
                {{ number_format($avgRating, 1) }}
            </span>
            <span class="mb-1 text-xl text-zinc-400">/5</span>
        </div>
        <div class="mt-2 flex gap-0.5">
            @for ($i = 1; $i <= 5; $i++)
                <flux:icon icon="{{ $i <= round($avgRating) ? 'star' : 'star' }}"
                    class="size-4 {{ $i <= round($avgRating) ? 'text-accent' : 'text-zinc-300 dark:text-zinc-600' }}" />
            @endfor
        </div>
    </div>
@endif

