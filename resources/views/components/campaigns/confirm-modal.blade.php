@props([
    'model',
    'title',
    'description',
    'confirmAction',
    'confirmLabel',
    'confirmVariant' => 'primary',
])

<flux:modal wire:model.self="{{ $model }}" class="md:w-[520px]">
    <flux:heading size="lg">{{ $title }}</flux:heading>
    <flux:text class="mt-2 text-zinc-500">{{ $description }}</flux:text>

    <div class="mt-6 flex gap-3">
        <flux:button variant="ghost" type="button" @click="$wire.set('{{ $model }}', false)">
            Cancel
        </flux:button>
        <flux:spacer />
        <flux:button variant="{{ $confirmVariant }}" wire:click="{{ $confirmAction }}">
            {{ $confirmLabel }}
        </flux:button>
    </div>
</flux:modal>