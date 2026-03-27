@props(['isCreator', 'isCreatorUsdView'])

<div class="mb-8 flex items-start justify-between">
    <div>
        <flux:heading size="xl">
            Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }},
            {{ auth()->user()->name }}
        </flux:heading>
        <flux:subheading class="mt-1">
            @if ($isCreator)
                Here's an overview of your creator business.
            @else
                Here's an overview of your brand campaigns.
            @endif
        </flux:subheading>
    </div>

    @if ($isCreator)
        <div class="flex items-center gap-2 rounded-lg border border-zinc-200 bg-white p-1 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:button wire:click="setCreatorRevenueCurrency('local')" size="sm"
                :variant="$isCreatorUsdView ? 'ghost' : 'primary'">
                Local
            </flux:button>
            <flux:button wire:click="setCreatorRevenueCurrency('usd')" size="sm"
                :variant="$isCreatorUsdView ? 'primary' : 'ghost'">
                USD
            </flux:button>
        </div>
    @endif
</div>

