@props([
    'product',
])

<flux:modal wire:model.self="showSlotModal" name="create-slot" class="md:w-96">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Add New Slot</flux:heading>
            <flux:text class="mt-2">Create a new available slot for this product.</flux:text>
        </div>

        <form wire:submit="createSlot" class="space-y-6">
            <flux:field>
                <flux:label>Slot Date</flux:label>
                <flux:input wire:model="slotForm.slot_date" type="date" required />
                <flux:error name="slotForm.slot_date" />
            </flux:field>

            <flux:field>
                <flux:label>Slot Time (Optional)</flux:label>
                <flux:input wire:model="slotForm.slot_time" type="time" />
                <flux:error name="slotForm.slot_time" />
            </flux:field>

            <flux:field>
                <flux:label>Price</flux:label>
                <flux:input wire:model="slotForm.price" type="number" step="0.01" min="0" required />
                <flux:error name="slotForm.price" />
            </flux:field>

            <flux:field>
                <flux:label>Notes (Optional)</flux:label>
                <flux:textarea wire:model="slotForm.notes" rows="3"
                    placeholder="Any special notes for this slot..." />
                <flux:error name="slotForm.notes" />
            </flux:field>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:loading.class="opacity-75">
                    <span wire:loading.remove>Create Slot</span>
                    <span wire:loading>Creating...</span>
                </flux:button>
            </div>
        </form>
    </div>
</flux:modal>
