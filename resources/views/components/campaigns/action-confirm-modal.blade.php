@props([
    'model',
    'title',
    'description',
    'confirmAction',
    'confirmLabel',
    'confirmVariant' => 'primary',
    'cancelLabel' => 'Cancel',
])

<flux:modal wire:model.self="{{ $model }}" class="md:w-[520px]">
    <flux:heading size="lg">{{ $title }}</flux:heading>
    <flux:text class="mt-2 text-zinc-500">{{ $description }}</flux:text>

    <div class="mt-6 flex gap-3">
        <flux:button variant="ghost" type="button" @click="$wire.set('{{ $model }}', false)">
            {{ $cancelLabel }}
        </flux:button>
        <flux:spacer />
        <flux:button variant="{{ $confirmVariant }}" wire:click="{{ $confirmAction }}">
            {{ $confirmLabel }}
        </flux:button>
    </div>
</flux:modal>
