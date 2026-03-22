@props([
    'model',
    'title' => 'Delete item?',
    'message' => 'This action cannot be undone.',
    'confirmAction',
    'confirmLabel' => 'Delete',
])

<flux:modal wire:model.self="{{ $model }}" class="max-w-md">
    <div class="space-y-5">
        <div>
            <flux:heading size="lg">{{ $title }}</flux:heading>
            <flux:text class="mt-2">{{ $message }}</flux:text>
        </div>

        <div class="flex gap-3">
            <flux:spacer />
            <flux:button variant="ghost" wire:click="$set('{{ $model }}', false)">Cancel</flux:button>
            <flux:button variant="danger" icon="trash" wire:click="{{ $confirmAction }}">{{ $confirmLabel }}</flux:button>
        </div>
    </div>
</flux:modal>
