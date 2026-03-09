@props(['booking'])

<flux:modal wire:model.self="showApproveModal" class="md:w-md">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Approve Work</flux:heading>
            <flux:text class="mt-2 text-zinc-500">This will release payment to the creator and mark the booking as complete.</flux:text>
        </div>

        <div class="flex gap-2">
            <flux:spacer />
            <flux:button variant="ghost" @click="$wire.set('showApproveModal', false)">
                Cancel
            </flux:button>
            <flux:button
                variant="primary"
                icon="check"
                wire:click="approveWork"
                wire:loading.attr="disabled"
                wire:loading.class="opacity-75"
            >
                <span wire:loading.remove wire:target="approveWork">Approve</span>
                <span wire:loading wire:target="approveWork">Approving…</span>
            </flux:button>
        </div>
    </div>
</flux:modal>
