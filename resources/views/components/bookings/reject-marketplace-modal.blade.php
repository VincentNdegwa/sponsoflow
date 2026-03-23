@props(['booking'])

<flux:modal wire:model.self="showMarketplaceRejectModal" class="md:w-lg">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Reject Marketplace Match</flux:heading>
            <flux:text class="mt-2 text-zinc-500">
                The brand will be notified that the marketplace match was not accepted. Add a brief note to explain why.
            </flux:text>
        </div>

        <form wire:submit="rejectMarketplaceApplication" class="space-y-6">
            <flux:field>
                <flux:label>Note to brand <flux:badge>Optional</flux:badge></flux:label>
                <flux:textarea
                    wire:model="marketplaceRejectionNote"
                    rows="3"
                    placeholder="e.g. The timeline doesn't align with our upcoming content calendar."
                />
                <flux:error name="marketplaceRejectionNote" />
            </flux:field>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:button variant="ghost" type="button" @click="$wire.set('showMarketplaceRejectModal', false)">
                    Cancel
                </flux:button>
                <flux:button
                    variant="danger"
                    type="submit"
                    icon="x-mark"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-75"
                >
                    <span wire:loading.remove wire:target="rejectMarketplaceApplication">Reject Match</span>
                    <span wire:loading wire:target="rejectMarketplaceApplication">Rejecting…</span>
                </flux:button>
            </div>
        </form>
    </div>
</flux:modal>
