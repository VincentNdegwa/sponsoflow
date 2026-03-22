<section class="rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
    <div class="mb-4 flex items-center justify-between gap-3">
        <div>
            <flux:heading size="lg">Campaign Brief Builder</flux:heading>
            <flux:text class="text-sm text-zinc-500">Design your questions, then answer them as the brand brief.</flux:text>
        </div>
        <flux:button type="button" icon="plus" variant="primary" wire:click="addBriefField">Add Question</flux:button>
    </div>

    <div class="space-y-4">
        @foreach($briefFields as $index => $field)
            @php
                $briefKey = (string) ($field['key'] ?? '');
                $briefType = (string) ($field['type'] ?? 'text');
            @endphp

            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" wire:key="brief-field-{{ $briefKey !== '' ? $briefKey : $index }}">
                <div class="grid gap-3 md:grid-cols-[1.8fr_1fr_auto]">
                    <flux:field>
                        <flux:label>Question Label</flux:label>
                        <flux:input wire:model.debounce.300ms="briefFields.{{ $index }}.label" placeholder="What is your campaign goal?" />
                        <flux:error name="briefFields.{{ $index }}.label" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Answer Type</flux:label>
                        <flux:select wire:model.live="briefFields.{{ $index }}.type">
                            <option value="text">Text</option>
                            <option value="textarea">Textarea</option>
                            <option value="number">Number</option>
                            <option value="date">Date</option>
                        </flux:select>
                    </flux:field>

                    <div class="flex items-end">
                        <flux:button icon="trash" size="sm" type="button" variant="danger" wire:click="removeBriefField({{ $index }})"></flux:button>
                    </div>
                </div>

                <div class="mt-3">
                    <flux:field>
                        <flux:label>Brand Answer</flux:label>

                        @if($briefKey === '')
                            <flux:input disabled placeholder="Question key is not available yet." />
                        @elseif($briefType === 'textarea')
                            <flux:textarea wire:model.debounce.300ms="contentBrief.{{ $briefKey }}" rows="3" />
                        @elseif($briefType === 'select')
                            <flux:select wire:model.change="contentBrief.{{ $briefKey }}">
                                <option value="">Select an option</option>
                                @foreach((array) ($field['options'] ?? []) as $option)
                                    <option value="{{ $option }}">{{ $option }}</option>
                                @endforeach
                            </flux:select>
                        @elseif($briefType === 'number')
                            <flux:input type="number" wire:model.debounce.300ms="contentBrief.{{ $briefKey }}" />
                        @elseif($briefType === 'date')
                            <flux:input type="date" wire:model.change="contentBrief.{{ $briefKey }}" />
                        @else
                            <flux:input wire:model.debounce.300ms="contentBrief.{{ $briefKey }}" />
                        @endif

                        @if($briefKey !== '')
                            <flux:error name="contentBrief.{{ $briefKey }}" />
                        @endif
                    </flux:field>
                </div>
            </div>
        @endforeach
    </div>
</section>
