@props(['booking', 'isBrandUser' => false])

@php
    $latestRating = $booking->latestRating;
@endphp

<div {{ $attributes->merge(['class' => 'rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800']) }}>
    <div class="mb-4 flex items-center gap-3">
        <div class="rounded-lg bg-zinc-100 p-2 dark:bg-zinc-900">
            <flux:icon icon="star" class="size-5 text-accent-content" />
        </div>
        <div>
            <flux:heading size="lg">Rating & Feedback</flux:heading>
            <flux:text class="text-xs text-zinc-500">Latest sentiment on delivered work</flux:text>
        </div>
    </div>

    @if(! $latestRating)
        <flux:text class="text-sm text-zinc-500">No rating submitted yet.</flux:text>
    @else
        <div class="space-y-3">
            <div class="flex items-center gap-2">
                <flux:text class="text-3xl font-semibold">{{ $latestRating->rating }}/5</flux:text>
                <flux:badge color="amber">Latest</flux:badge>
            </div>

            @if($latestRating->tags)
                <div class="flex flex-wrap gap-2">
                    @foreach($latestRating->tags as $tag)
                        <flux:badge size="sm" color="zinc">{{ $tag }}</flux:badge>
                    @endforeach
                </div>
            @endif

            @if($latestRating->comment)
                <div class="rounded-md bg-zinc-50 p-3 dark:bg-zinc-900">
                    <flux:text class="text-sm">{{ $latestRating->comment }}</flux:text>
                </div>
            @endif

            <flux:text class="text-xs text-zinc-500">
                {{ $isBrandUser ? 'You' : 'Brand' }} rated this booking {{ $latestRating->created_at->diffForHumans() }}.
            </flux:text>
        </div>
    @endif
</div>
