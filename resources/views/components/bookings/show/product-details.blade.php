@props(['booking', 'isBrandUser' => false])

@php
    $product = $booking->product;
    $workspaceCurrency = $booking->workspace?->currency ?? $booking->currency ?? 'USD';
@endphp

<div {{ $attributes->merge(['class' => 'rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800']) }}>
    <div class="mb-4 flex items-center gap-3">
        <div class="rounded-lg bg-zinc-100 p-2 dark:bg-zinc-900">
            <flux:icon icon="cube" class="size-5 text-accent-content" />
        </div>
        <div>
            <flux:heading size="lg">Product Details</flux:heading>
            <flux:text class="text-xs text-zinc-500">Offer structure and requirements baseline</flux:text>
        </div>
    </div>

    <div class="space-y-3">
        @if($product)
            <div class="mb-2 flex flex-wrap gap-2">
                <flux:badge size="sm" color="zinc">{{ strtoupper((string) $workspaceCurrency) }}</flux:badge>
                <flux:badge size="sm" color="zinc">{{ $product->requirements->count() }} requirements</flux:badge>
                @if($product->is_public)
                    <flux:badge size="sm" color="green">Public</flux:badge>
                @endif
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">Product</flux:text>
                <flux:text class="mt-1 font-medium">{{ $product?->name ?? 'N/A' }}</flux:text>
            </div>
            <div>
                <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">Type</flux:text>
                <flux:text class="mt-1">{{ ucfirst((string) ($product?->type ?? 'N/A')) }}</flux:text>
            </div>
            <div>
                <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">Base Price</flux:text>
                <flux:text class="mt-1">
                    {{ $product ? \App\Support\CurrencySupport::formatCurrency((float) $product->base_price, $workspaceCurrency) : 'N/A' }}
                </flux:text>
            </div>
            <div>
                <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">Duration</flux:text>
                <flux:text class="mt-1">{{ $product?->duration_minutes ? $product->duration_minutes . ' mins' : 'N/A' }}</flux:text>
            </div>
        </div>

        @if($product?->description)
            <div class="rounded-md bg-zinc-50 p-3 dark:bg-zinc-900">
                <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">Description</flux:text>
                <flux:text class="mt-1 text-sm">{{ $product->description }}</flux:text>
            </div>
        @endif

        <div class="rounded-md bg-zinc-50 p-3 dark:bg-zinc-900">
            <div class="mb-2 flex items-center gap-2">
                <flux:icon icon="calendar-days" class="size-4 text-accent-content" />
                <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">Slot Details</flux:text>
            </div>

            @if(! $booking->slot)
                <flux:text class="text-sm text-zinc-500">This booking does not use a fixed slot.</flux:text>
            @else
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
                    <div class="mt-3 rounded-md bg-white p-3 dark:bg-zinc-800">
                        <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">Slot Notes</flux:text>
                        <flux:text class="mt-1 text-sm">{{ $booking->slot->notes }}</flux:text>
                    </div>
                @endif
            @endif
        </div>

        @if($product)
            <div class="flex items-center justify-between text-sm">
                <flux:text class="text-zinc-500">Requirements</flux:text>
                <flux:text>{{ $product->requirements->count() }}</flux:text>
            </div>
        @endif
    </div>
</div>
