@props([
    'model',
    'title' => 'Copy item?',
    'message' => 'This will create a workspace copy you can edit and delete.',
    'confirmAction',
    'confirmLabel' => 'Copy',
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
            <flux:button variant="primary" icon="document-duplicate" wire:click="{{ $confirmAction }}">{{ $confirmLabel }}</flux:button>
        </div>
    </div>
</flux:modal>
