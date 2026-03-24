@props([
    'campaign',
    'application' => null,
    'isCreator' => false,
    'previewMode' => false,
])

@php
    $hasApplied = $application !== null;
    $applicationsPaused = $campaign->status?->value === 'paused';
    $description = $campaign->description;
    $postedAt = $campaign->posted_at ?? $campaign->created_at;

    $deliverables = is_array($campaign->deliverables) ? $campaign->deliverables : [];
    $budget = 0.0;

    foreach ($deliverables as $row) {
        $subtotal = data_get($row, 'subtotal');
        if ($subtotal === null) {
            $qty = (int) data_get($row, 'qty', data_get($row, 'quantity', 0));
            $unitPrice = (float) data_get($row, 'unit_price', 0);
            $subtotal = $qty * $unitPrice;
        }

        $budget += (float) $subtotal;
    }

    if ($budget <= 0) {
        $budget = (float) $campaign->total_budget;
    }

    $displayStatus = $application?->display_status ?? $application?->status?->label();
    $displayColor = $displayStatus === 'Booked'
        ? 'green'
        : ($application?->status?->badgeColor() ?? 'zinc');
@endphp

<article class="group flex h-full flex-col rounded-lg border border-zinc-200 bg-white p-6 transition-all hover:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-900">
    <div class="flex items-start justify-between gap-3">
        <div class="space-y-2">
            <div class="flex items-center gap-2">
                <flux:badge size="sm" :color="$campaign->status->badgeColor()" inset="top bottom">
                    {{ $campaign->status->label() }}
                </flux:badge>
            </div>
            <flux:heading size="md" class="font-semibold">
                <a href="{{ route('marketplace.campaigns.show', $campaign) }}" class="hover:underline" target="_blank" rel="noopener">
                    {{ $campaign->title }}
                </a>
            </flux:heading>
            <flux:text class="text-xs text-zinc-500">Posted by: {{ $campaign->workspace->name }}</flux:text>
        </div>
        <flux:button
            variant="ghost"
            size="sm"
            icon="arrow-top-right-on-square"
            :href="route('marketplace.campaigns.show', $campaign)"
            target="_blank"
            rel="noopener"
        />
    </div>

    <flux:text class="mt-4 text-sm text-zinc-600 dark:text-zinc-400">
        {{ $description ? Str::limit($description, 140) : 'Description coming soon.' }}
    </flux:text>

    <div class="mt-5 rounded-md border border-zinc-100 bg-zinc-50 px-3 py-3 text-sm dark:border-zinc-800 dark:bg-zinc-950">
        <flux:text class="text-xs uppercase tracking-wide text-zinc-500">Budget</flux:text>
        <flux:text class="mt-1 font-semibold text-zinc-900 dark:text-zinc-100">
            {{ formatMoney($budget, $campaign->workspace) }}
        </flux:text>
    </div>

    <div class="mt-5 flex items-center justify-between text-xs text-zinc-500">
        <span>Posted on: {{ formatWorkspaceDate($postedAt) }}</span>
    </div>

    <div class="mt-6 flex items-center justify-between gap-3">
        @if($isCreator)
            @if($hasApplied)
                <div class="flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700 dark:border-amber-900 dark:bg-amber-900/30 dark:text-amber-200">
                    <span>Application:</span>
                    <flux:badge size="xs" :color="$displayColor">
                        {{ $displayStatus }}
                    </flux:badge>
                </div>
            @elseif($applicationsPaused)
                <div class="rounded-md border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800">
                    Applications paused
                </div>
            @else
                @if($previewMode)
                    <flux:button variant="primary" size="sm" icon="paper-airplane" disabled>
                        Apply
                    </flux:button>
                @else
                    <flux:button variant="primary" size="sm" wire:click="openApplyModal({{ $campaign->id }})" icon="paper-airplane">
                        Apply
                    </flux:button>
                @endif
            @endif
        @else
            <flux:text class="text-xs text-zinc-500">Creators can apply.</flux:text>
        @endif

        <a href="{{ route('marketplace.campaigns.show', $campaign) }}" class="text-xs font-semibold uppercase tracking-wide text-amber-700 hover:text-amber-800 dark:text-amber-200" target="_blank" rel="noopener">
            View Details
        </a>
    </div>
</article>
