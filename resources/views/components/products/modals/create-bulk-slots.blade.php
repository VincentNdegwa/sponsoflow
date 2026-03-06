@props([
    'product',
    'batchForm' => [],
    'batchPreview' => [],
])

<flux:modal wire:model.self="showBatchModal" name="batch-generate" class="md:w-[42rem]">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Batch Generate Slots</flux:heading>
            <flux:text class="mt-2">Quickly fill your calendar with inventory slots using automated scheduling.</flux:text>
        </div>

        <form wire:submit="generateBatchSlots" class="space-y-6">
            <div class="grid gap-6 md:grid-cols-2">
                <flux:field>
                    <flux:label>Start Date</flux:label>
                    <flux:input wire:model="batchForm.start_date" type="date" required />
                    <flux:error name="batchForm.start_date" />
                </flux:field>
    
                <flux:field>
                    <flux:label>End Date</flux:label>
                    <flux:input wire:model="batchForm.end_date" type="date" required />
                    <flux:error name="batchForm.end_date" />
                </flux:field>
            </div>
    
            <div class="grid gap-6 md:grid-cols-2">
                <flux:field>
                    <flux:label>Frequency</flux:label>
                    <flux:select wire:model.live="batchForm.frequency" required>
                        <flux:select.option value="daily">Daily</flux:select.option>
                        <flux:select.option value="weekly">Weekly</flux:select.option>
                        <flux:select.option value="monthly">Monthly</flux:select.option>
                    </flux:select>
                    <flux:error name="batchForm.frequency" />
                </flux:field>
    
                @if ($batchForm['frequency'] === 'weekly')
                    <flux:field>
                        <flux:label>Day of Week</flux:label>
                        <flux:select wire:model="batchForm.day_of_week" required>
                            <flux:select.option value="1">Monday</flux:select.option>
                            <flux:select.option value="2">Tuesday</flux:select.option>
                            <flux:select.option value="3">Wednesday</flux:select.option>
                            <flux:select.option value="4">Thursday</flux:select.option>
                            <flux:select.option value="5">Friday</flux:select.option>
                            <flux:select.option value="6">Saturday</flux:select.option>
                            <flux:select.option value="0">Sunday</flux:select.option>
                        </flux:select>
                        <flux:error name="batchForm.day_of_week" />
                    </flux:field>
                @elseif($batchForm['frequency'] === 'monthly')
                    <flux:field>
                        <flux:label>Day of Month</flux:label>
                        <flux:input wire:model="batchForm.day_of_month" type="number" min="1" max="31"
                            placeholder="15" required />
                        <flux:error name="batchForm.day_of_month" />
                    </flux:field>
                @endif
            </div>
    
            <flux:field>
                <flux:label>Price Override (Optional)</flux:label>
                <flux:input wire:model="batchForm.price_override" type="number" step="0.01" min="0"
                    placeholder="Leave blank to use base price of ${{ number_format($product->base_price, 2) }}" />
                <flux:error name="batchForm.price_override" />
            </flux:field>
    
            <flux:field>
                <flux:label>Notes Template (Optional)</flux:label>
                <flux:textarea wire:model="batchForm.notes_template" rows="2"
                    placeholder="Batch generated slot for {{ $product->name }}" />
                <flux:error name="batchForm.notes_template" />
            </flux:field>
    
            @if ($batchPreview && count($batchPreview) > 0)
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
                    <flux:heading size="sm" class="mb-3 text-blue-800 dark:text-blue-200">
                        Preview: {{ count($batchPreview) }} slots will be created
                    </flux:heading>
    
                    <div class="grid gap-2 max-h-40 overflow-y-auto">
                        @foreach (array_slice($batchPreview, 0, 5) as $date)
                            <div class="flex justify-between text-sm">
                                <span class="text-blue-700 dark:text-blue-300">{{ $date }}</span>
                                <span class="text-blue-600 dark:text-blue-400">
                                    ${{ number_format($batchForm['price_override'] ?: $product->base_price, 2) }}
                                </span>
                            </div>
                        @endforeach
                        @if (count($batchPreview) > 5)
                            <div class="text-center text-xs text-blue-600 dark:text-blue-400">
                                ... and {{ count($batchPreview) - 5 }} more
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:loading.class="opacity-75"
                    :disabled="!$batchPreview || count($batchPreview) === 0">
                    <span wire:loading.remove>Generate {{ count($batchPreview ?? []) }} Slots</span>
                    <span wire:loading>Generating...</span>
                </flux:button>
            </div>
        </form>
    </div>
</flux:modal>
