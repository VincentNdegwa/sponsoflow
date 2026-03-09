@props(['booking'])

<flux:modal wire:model.self="showDisputeForm" class="md:w-lg">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Open a Dispute</flux:heading>
            <flux:text class="mt-2 text-zinc-500">Describe the issue. Our team will review within 48 hours.</flux:text>
        </div>

        <form wire:submit="openDispute" class="space-y-6">
            <flux:field>
                <flux:label>Reason</flux:label>
                <flux:textarea
                    wire:model="disputeReason"
                    rows="4"
                    placeholder="What was agreed upon, what was delivered, and what steps you've already taken…"
                />
                <flux:error name="disputeReason" />
            </flux:field>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:button variant="ghost" type="button" @click="$wire.set('showDisputeForm', false)">
                    Cancel
                </flux:button>
                <flux:button
                    variant="danger"
                    type="submit"
                    icon="shield-exclamation"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-75"
                >
                    <span wire:loading.remove wire:target="openDispute">Open Dispute</span>
                    <span wire:loading wire:target="openDispute">Opening…</span>
                </flux:button>
            </div>
        </form>
    </div>
</flux:modal>
