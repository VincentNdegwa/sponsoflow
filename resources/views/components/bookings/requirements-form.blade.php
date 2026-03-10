@props([
    'requirements',
    'model' => 'requirementData',
    'emptyState' => true,
])

@if($requirements->isNotEmpty())
    <div class="space-y-5">
        @foreach($requirements as $requirement)
            <flux:field>
                <flux:label>
                    {{ $requirement->name }}
                    @if($requirement->is_required)
                        <span class="text-red-500">*</span>
                    @endif
                </flux:label>

                @if($requirement->description)
                    <flux:description>{{ $requirement->description }}</flux:description>
                @endif

                @if($requirement->type === 'textarea')
                    <flux:textarea
                        wire:model="{{ $model }}.{{ $requirement->id }}"
                        rows="3"
                    />
                @else
                    <flux:input
                        wire:model="{{ $model }}.{{ $requirement->id }}"
                        :type="$requirement->type"
                    />
                @endif

                <flux:error name="{{ $model }}.{{ $requirement->id }}" />
            </flux:field>
        @endforeach
    </div>
@elseif($emptyState)
    <flux:callout variant="info" icon="information-circle">
        <flux:callout.text>No additional information is required. Click below to proceed to payment.</flux:callout.text>
    </flux:callout>
@endif
