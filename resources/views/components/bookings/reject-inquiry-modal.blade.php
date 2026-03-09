@props(['booking'])

<flux:modal wire:model.self="showRejectModal" class="md:w-lg">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Reject Inquiry</flux:heading>
            <flux:text class="mt-2 text-zinc-500">
                The brand will be notified that their inquiry was not accepted. An optional note helps them understand why.
            </flux:text>
        </div>

        <form wire:submit="rejectInquiry" class="space-y-6">
            <flux:field>
                <flux:label>Note to brand <flux:badge>Optional</flux:badge></flux:label>
                <flux:textarea
                    wire:model="rejectionNote"
                    rows="3"
                    placeholder="e.g. This slot is no longer available, or the budget doesn't meet our minimum requirement…"
                />
                <flux:error name="rejectionNote" />
            </flux:field>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:button variant="ghost" type="button" @click="$wire.set('showRejectModal', false)">
                    Cancel
                </flux:button>
                <flux:button
                    variant="danger"
                    type="submit"
                    icon="x-mark"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-75"
                >
                    <span wire:loading.remove wire:target="rejectInquiry">Reject Inquiry</span>
                    <span wire:loading wire:target="rejectInquiry">Rejecting…</span>
                </flux:button>
            </div>
        </form>
    </div>
</flux:modal>
