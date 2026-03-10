@props(['booking'])

<flux:modal wire:model.self="showCounterModal" class="md:w-lg">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Send a Counter-Offer</flux:heading>
            <flux:text class="mt-2 text-zinc-500">
                The brand offered <strong class="text-zinc-700 dark:text-zinc-200">{{ $booking->formatAmount() }}</strong>.
                Propose a different amount and optionally explain your reasoning.
            </flux:text>
        </div>

        <form wire:submit="counterInquiry" class="space-y-6">
            <flux:field>
                <flux:label>Your Counter Amount</flux:label>
                <flux:input
                    wire:model="counterAmount"
                    type="number"
                    step="0.01"
                    min="1"
                    placeholder="e.g. 500.00"
                    prefix="{{ config('cashier.currency', 'USD') === 'USD' ? '$' : config('cashier.currency', 'USD') }}"
                />
                <flux:error name="counterAmount" />
            </flux:field>

            <flux:field>
                <flux:label>Message to brand (Optional)</flux:label>
                <flux:textarea
                    wire:model="counterNote"
                    rows="3"
                    placeholder="e.g. Our standard rate for this format includes two rounds of revisions and…"
                />
                <flux:error name="counterNote" />
            </flux:field>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:button variant="ghost" type="button" @click="$wire.set('showCounterModal', false)">
                    Cancel
                </flux:button>
                <flux:button
                    variant="primary"
                    type="submit"
                    icon="arrow-path"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-75"
                >
                    <span wire:loading.remove wire:target="counterInquiry">Send Counter-Offer</span>
                    <span wire:loading wire:target="counterInquiry">Sending…</span>
                </flux:button>
            </div>
        </form>
    </div>
</flux:modal>
