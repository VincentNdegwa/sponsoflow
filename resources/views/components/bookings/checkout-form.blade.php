@props([
    'requirements',
    'action',
    'error' => null,
    'backAction' => null,
    'backLabel' => '← Back',
])

<form wire:submit="{{ $action }}" class="space-y-6">
    @isset($guestFields)
        {{ $guestFields }}
    @endisset

    <x-bookings.requirements-form :requirements="$requirements" />

    @if($error)
        <flux:callout variant="danger" icon="exclamation-triangle">
            <flux:callout.text>{{ $error }}</flux:callout.text>
        </flux:callout>
    @endif

    <flux:button
        type="submit"
        variant="primary"
        class="w-full"
        icon-trailing="arrow-right"
        wire:loading.attr="disabled"
        wire:loading.class="opacity-75"
    >
        <span wire:loading.remove wire:target="{{ $action }}">Proceed to Secure Payment</span>
        <span wire:loading wire:target="{{ $action }}">Preparing checkout…</span>
    </flux:button>

    @if($backAction)
        <div class="text-center">
            <flux:button
                wire:click="{{ $backAction }}"
                variant="ghost"
                size="sm"
            >
                {{ $backLabel }}
            </flux:button>
        </div>
    @endif
</form>
