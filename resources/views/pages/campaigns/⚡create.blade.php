<?php

use App\Models\CampaignTemplate;
use App\Models\DeliverableOption;
use App\Services\CampaignService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app'), Title('Create Campaign')] class extends Component {
    public ?int $templateId = null;
    public string $title = '';
    public bool $isPublic = false;

    public array $briefFields = [];
    public array $contentBrief = [];
    public array $deliverables = [];

    public function mount(): void
    {
        $workspace = currentWorkspace();

        if (! $workspace || ! $workspace->isBrand()) {
            abort(403);
        }

        $this->isPublic = false;

        if ($this->briefFields === []) {
            $this->addBriefField([
                'label' => 'Campaign Goal',
                'type' => 'text',
                'options' => [],
            ]);
        }
    }

    #[Computed]
    public function availableTemplates(): \Illuminate\Database\Eloquent\Collection
    {
        $workspaceId = currentWorkspace()?->id;

        return CampaignTemplate::query()
            ->with('category')
            ->where(function ($query) use ($workspaceId) {
                $query->whereNull('workspace_id');

                if ($workspaceId) {
                    $query->orWhere('workspace_id', $workspaceId);
                }
            })
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function deliverableOptions(): \Illuminate\Database\Eloquent\Collection
    {
        $workspaceId = currentWorkspace()?->id;

        return DeliverableOption::query()
            ->where(function ($query) use ($workspaceId) {
                $query->whereNull('workspace_id');

                if ($workspaceId) {
                    $query->orWhere('workspace_id', $workspaceId);
                }
            })
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function selectedTemplate(): ?CampaignTemplate
    {
        if (! $this->templateId) {
            return null;
        }

        return $this->availableTemplates->first(fn (CampaignTemplate $template) => (int) $template->id === (int) $this->templateId);
    }

    #[Computed]
    public function totalBudget(): float
    {
        return round(collect($this->deliverables)->sum(function (array $row) {
            return (max(0, (int) ($row['qty'] ?? 0)) * max(0, (float) ($row['unit_price'] ?? 0)));
        }), 2);
    }

    public function updatedTemplateId(mixed $value): void
    {
        $this->templateId = ($value === null || $value === '') ? null : (int) $value;

        $template = $this->selectedTemplate;

        $this->briefFields = [];
        $this->contentBrief = [];
        $this->deliverables = [];

        if (! $template) {
            $this->addBriefField();

            return;
        }

        foreach ((array) data_get($template->form_schema, 'sections', []) as $section) {
            foreach ((array) data_get($section, 'fields', []) as $field) {
                $name = (string) data_get($field, 'name');

                if ($name !== '') {
                    $normalizedKey = $this->generateBriefFieldKey($name, count($this->briefFields));

                    $options = array_values((array) data_get($field, 'options', []));

                    $this->briefFields[] = [
                        'key' => $normalizedKey,
                        'label' => (string) data_get($field, 'label', $name),
                        'type' => (string) data_get($field, 'type', 'text'),
                        'options' => $options,
                        'options_text' => implode(', ', $options),
                    ];

                    $this->contentBrief[$normalizedKey] = '';
                }
            }
        }

        foreach ((array) ($template->deliverable_options ?? []) as $defaultDeliverable) {
            $optionId = data_get($defaultDeliverable, 'deliverable_option_id');

            if (! $optionId) {
                continue;
            }

            $this->addDeliverable((int) $optionId, (array) $defaultDeliverable);
        }
    }

    public function addDeliverable(?int $optionId = null, array $defaults = []): void
    {
        $selectedOptionId = $optionId;

        if (! $selectedOptionId) {
            $selectedOptionId = (int) $this->deliverableOptions->first()?->id;
        }

        if (! $selectedOptionId) {
            return;
        }

        $option = $this->deliverableOptions->firstWhere('id', $selectedOptionId);

        if (! $option) {
            return;
        }

        $this->deliverables[] = $this->buildDeliverableRow($option, count($this->deliverables), $defaults);
    }

    public function addBriefField(array $defaults = []): void
    {
        $index = count($this->briefFields) + 1;
        $label = (string) data_get($defaults, 'label', 'Question '.$index);
        $options = array_values((array) data_get($defaults, 'options', []));
        $generatedKey = $this->generateBriefFieldKey((string) data_get($defaults, 'key', $label), count($this->briefFields));

        $this->briefFields[] = [
            'key' => $generatedKey,
            'label' => $label,
            'type' => (string) data_get($defaults, 'type', 'text'),
            'options' => $options,
            'options_text' => implode(', ', $options),
        ];

        $this->contentBrief[$generatedKey] = $this->contentBrief[$generatedKey] ?? '';
    }

    public function removeBriefField(int $index): void
    {
        if (! isset($this->briefFields[$index])) {
            return;
        }

        $fieldKey = (string) ($this->briefFields[$index]['key'] ?? '');

        unset($this->briefFields[$index]);
        $this->briefFields = array_values($this->briefFields);

        if ($fieldKey !== '' && array_key_exists($fieldKey, $this->contentBrief)) {
            unset($this->contentBrief[$fieldKey]);
        }
    }

    public function updatedDeliverables($value, mixed $path = null): void
    {
        if (! is_string($path) || $path === '') {
            return;
        }

        if (str_ends_with($path, '.deliverable_option_id')) {
            $parts = explode('.', $path);
            $index = (int) ($parts[1] ?? -1);

            if (! isset($this->deliverables[$index])) {
                return;
            }

            $option = $this->deliverableOptions->firstWhere('id', (int) ($this->deliverables[$index]['deliverable_option_id'] ?? 0));

            if (! $option) {
                return;
            }

            $defaults = [
                'id' => $this->deliverables[$index]['id'] ?? null,
                'quantity' => $this->deliverables[$index]['qty'] ?? 1,
                'unit_price' => $this->deliverables[$index]['unit_price'] ?? 0,
                'fields' => (array) ($this->deliverables[$index]['fields'] ?? []),
            ];

            $this->deliverables[$index] = $this->buildDeliverableRow($option, $index, $defaults);

            return;
        }

        if (! str_ends_with($path, '.qty') && ! str_ends_with($path, '.unit_price')) {
            return;
        }

        $parts = explode('.', $path);
        $index = (int) ($parts[1] ?? -1);

        if (! isset($this->deliverables[$index])) {
            return;
        }

        $quantity = max(1, (int) ($this->deliverables[$index]['qty'] ?? 1));
        $unitPrice = max(0, round((float) ($this->deliverables[$index]['unit_price'] ?? 0), 2));

        $this->deliverables[$index]['qty'] = $quantity;
        $this->deliverables[$index]['unit_price'] = $unitPrice;
        $this->deliverables[$index]['fields']['quantity'] = $quantity;
        $this->deliverables[$index]['fields']['unit_price'] = $unitPrice;
    }

    public function updatedBriefFields($value, mixed $path = null): void
    {
        if (! is_string($path) || $path === '') {
            return;
        }

        $parts = explode('.', $path);
        $index = (int) ($parts[1] ?? -1);
        $attribute = (string) ($parts[2] ?? '');

        if (! isset($this->briefFields[$index])) {
            return;
        }

        if ($attribute === 'options_text') {
            $rawOptions = (string) ($this->briefFields[$index]['options_text'] ?? '');
            $this->briefFields[$index]['options'] = array_values(array_filter(array_map('trim', explode(',', $rawOptions))));
        }
    }

    public function removeDeliverable(int $index): void
    {
        if (! isset($this->deliverables[$index])) {
            return;
        }

        unset($this->deliverables[$index]);
        $this->deliverables = array_values($this->deliverables);
    }

    public function submit(): void
    {
        $this->validate([
            'templateId' => 'nullable|integer|exists:campaign_templates,id',
            'title' => 'required|string|min:3|max:150',
            'briefFields' => 'required|array|min:1',
            'briefFields.*.key' => 'required|string|min:2|max:120|regex:/^[a-zA-Z_][a-zA-Z0-9_]*$/',
            'briefFields.*.label' => 'required|string|min:2|max:120',
            'briefFields.*.type' => 'required|string|in:text,textarea,select,number,date',
            'deliverables' => 'required|array|min:1',
            'deliverables.*.deliverable_option_id' => 'required|integer|exists:deliverable_options,id',
            'deliverables.*.type_slug' => 'required|string|max:120',
            'deliverables.*.label' => 'required|string|max:120',
            'deliverables.*.qty' => 'required|integer|min:1',
            'deliverables.*.unit_price' => 'required|numeric|min:0',
            'deliverables.*.fields' => 'nullable|array',
        ]);

        $brandWorkspace = currentWorkspace();

        if (! $brandWorkspace || ! $brandWorkspace->isBrand()) {
            abort(403);
        }

        $template = null;

        if ($this->templateId) {
            $template = CampaignTemplate::query()
                ->where('id', $this->templateId)
                ->where(function ($query) use ($brandWorkspace) {
                    $query->whereNull('workspace_id')
                        ->orWhere('workspace_id', $brandWorkspace->id);
                })
                ->firstOrFail();
        }

        if (! $this->validateContentBrief()) {
            return;
        }

        if (! $this->validateDeliverableValues()) {
            return;
        }

        if (! $this->validateDeliverableFields()) {
            return;
        }

        $formSchema = [
            'sections' => [
                [
                    'title' => 'Campaign Details',
                    'fields' => collect($this->briefFields)->map(fn (array $field) => [
                        'name' => (string) $field['key'],
                        'type' => (string) $field['type'],
                        'label' => (string) $field['label'],
                        'options' => $field['type'] === 'select' ? array_values((array) ($field['options'] ?? [])) : [],
                    ])->values()->all(),
                ],
            ],
        ];

        $contentBriefAnswers = [];

        foreach ($this->briefFields as $field) {
            $fieldKey = (string) ($field['key'] ?? '');

            if ($fieldKey === '') {
                continue;
            }

            $contentBriefAnswers[$fieldKey] = $this->contentBrief[$fieldKey] ?? '';
        }

        $contentBriefPayload = array_merge($contentBriefAnswers, [
            '_form_schema' => $formSchema,
        ]);

        app(CampaignService::class)->createCampaign(
            template: $template,
            contentBrief: $contentBriefPayload,
            deliverables: $this->deliverables,
            title: $this->title,
            isPublic: $this->isPublic,
        );

        $this->dispatch('success', 'Campaign created successfully.');
        $this->redirect(route('campaigns.index'), navigate: true);
    }

    private function validateContentBrief(): bool
    {
        $isValid = true;

        foreach ($this->briefFields as $field) {
            $name = (string) ($field['key'] ?? '');
            $label = (string) ($field['label'] ?? $name);
            $value = trim((string) ($this->contentBrief[$name] ?? ''));

            if ($value === '') {
                $this->addError('contentBrief.'.$name, $label.' is required.');
                $isValid = false;
            }
        }

        return $isValid;
    }

    private function validateDeliverableValues(): bool
    {
        $isValid = true;

        foreach ($this->deliverables as $index => $row) {
            $quantity = (int) ($row['qty'] ?? 0);
            $unitPrice = (float) ($row['unit_price'] ?? 0);

            if ($quantity < 1) {
                $this->addError('deliverables.'.$index.'.qty', 'Quantity must be at least 1.');
                $isValid = false;
            }

            if ($unitPrice < 0) {
                $this->addError('deliverables.'.$index.'.unit_price', 'Unit price cannot be negative.');
                $isValid = false;
            }
        }

        return $isValid;
    }

    private function validateDeliverableFields(): bool
    {
        $isValid = true;

        foreach ($this->deliverables as $index => $row) {
            foreach ((array) ($row['field_definitions'] ?? []) as $definition) {
                $fieldKey = (string) data_get($definition, 'key', '');
                $fieldLabel = (string) data_get($definition, 'label', $fieldKey);
                $required = (bool) data_get($definition, 'required', false);

                if ($fieldKey === '' || ! $required) {
                    continue;
                }

                $value = data_get($row, 'fields.'.$fieldKey);

                if ($value === null || $value === '') {
                    $this->addError('deliverables.'.$index.'.fields.'.$fieldKey, $fieldLabel.' is required.');
                    $isValid = false;
                }
            }
        }

        return $isValid;
    }

    private function buildDeliverableRow(DeliverableOption $option, int $index, array $defaults = []): array
    {
        $fieldDefinitions = (array) ($option->fields ?? []);
        $fieldValues = [];

        foreach ($fieldDefinitions as $fieldDefinition) {
            $fieldKey = (string) data_get($fieldDefinition, 'key', '');

            if ($fieldKey === '') {
                continue;
            }

            $fieldValues[$fieldKey] = data_get($defaults, 'fields.'.$fieldKey, data_get($fieldDefinition, 'default', ''));
        }

        $quantity = max(1, (int) data_get($defaults, 'quantity', data_get($defaults, 'fields.quantity', data_get($defaults, 'qty', 1))));
        $unitPrice = max(0, round((float) data_get($defaults, 'unit_price', data_get($defaults, 'fields.unit_price', 0)), 2));

        $fieldValues['quantity'] = $quantity;
        $fieldValues['unit_price'] = $unitPrice;

        return [
            'id' => (string) data_get($defaults, 'id', str_replace('.', '', uniqid('row_', true))),
            'deliverable_option_id' => $option->id,
            'type_slug' => $option->slug,
            'label' => $option->name,
            'qty' => $quantity,
            'unit_price' => $unitPrice,
            'fields' => $fieldValues,
            'field_definitions' => $fieldDefinitions,
            'status' => 'pending',
            'proof_url' => null,
        ];
    }

    private function normalizeFieldKey(string $key): string
    {
        $normalized = strtolower(trim($key));
        $normalized = preg_replace('/[^a-z0-9_]+/', '_', $normalized) ?: '';

        return trim($normalized, '_');
    }

    private function generateBriefFieldKey(string $label, int $index): string
    {
        $base = $this->normalizeFieldKey($label);

        if ($base === '') {
            $base = 'field_'.($index + 1);
        }

        $base = substr($base, 0, 36);
        $candidate = $base;
        $counter = 1;

        while ($this->isBriefFieldKeyUsed($candidate, $index)) {
            $suffix = '_'.$counter;
            $candidate = substr($base, 0, max(1, 36 - strlen($suffix))).$suffix;
            $counter++;
        }

        return $candidate;
    }

    private function isBriefFieldKeyUsed(string $key, int $currentIndex): bool
    {
        foreach ($this->briefFields as $index => $field) {
            if ($index === $currentIndex) {
                continue;
            }

            if ((string) ($field['key'] ?? '') === $key) {
                return true;
            }
        }

        return false;
    }
}; ?>

<div>
    <div class="mb-8 flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Create Campaign</flux:heading>
            <flux:subheading>Create a brand campaign brief and define deliverables.</flux:subheading>
        </div>

        <flux:button variant="ghost" href="{{ route('campaigns.index') }}">
            Back to Campaigns
        </flux:button>
    </div>

    <form wire:submit="submit" class="space-y-6">
        <section class="rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
            <flux:heading size="lg" class="mb-4">Campaign Setup</flux:heading>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:field>
                    <flux:label>Template (Optional)</flux:label>
                    <flux:select wire:model.live="templateId">
                        <option value="">Start from scratch</option>
                        @foreach($this->availableTemplates as $template)
                            <option value="{{ $template->id }}">
                                {{ $template->name }}
                                @if($template->category)
                                    - {{ $template->category->name }}
                                @endif
                            </option>
                        @endforeach
                    </flux:select>
                    <flux:error name="templateId" />
                </flux:field>

                <flux:field>
                    <flux:label>Campaign Title</flux:label>
                    <flux:input wire:model.blur="title" placeholder="e.g. Q2 Sneaker Launch" />
                    <flux:error name="title" />
                </flux:field>
            </div>

            <div class="mt-4">
                <flux:field>
                    <flux:label>Visibility</flux:label>
                    <div class="mt-2 flex items-center gap-3">
                        <flux:switch wire:model="isPublic" />
                        <flux:text class="text-sm text-zinc-500">Campaigns created from inquiry flows should remain private by default.</flux:text>
                    </div>
                </flux:field>
            </div>
        </section>

        @include('components.campaigns.brief-builder')

        <section class="rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
            <div class="mb-4 flex items-center justify-between gap-3">
                <flux:heading size="lg">Deliverables</flux:heading>
                <flux:button
                    type="button"
                    variant="primary"
                    icon="plus"
                    wire:click="addDeliverable"
                    :disabled="$this->deliverableOptions->isEmpty()"
                >
                    Add Deliverable
                </flux:button>
            </div>

            @if($this->deliverableOptions->isEmpty())
                <div class="rounded-xl border border-dashed border-zinc-300 p-5 text-center text-sm text-zinc-500 dark:border-zinc-700">
                    No deliverable types are available for this workspace.
                </div>
            @endif

            <div class="space-y-3">
                @forelse($deliverables as $index => $row)
                    <div class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" wire:key="deliverable-{{ $row['id'] }}">
                        <div class="grid gap-3 md:grid-cols-[1.6fr_0.9fr_0.9fr_auto_auto]">
                            <flux:field>
                                <flux:label>Type</flux:label>
                                <flux:select wire:model.change="deliverables.{{ $index }}.deliverable_option_id">
                                    @foreach($this->deliverableOptions as $option)
                                        <option value="{{ $option->id }}">{{ $option->name }}</option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="deliverables.{{ $index }}.deliverable_option_id" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Qty</flux:label>
                                <flux:input type="number" min="0" wire:model.live="deliverables.{{ $index }}.qty" />
                                <flux:error name="deliverables.{{ $index }}.qty" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Unit Price</flux:label>
                                <flux:input type="number" min="0" step="0.01" wire:model.live="deliverables.{{ $index }}.unit_price" />
                                <flux:error name="deliverables.{{ $index }}.unit_price" />
                            </flux:field>

                            <div class="flex items-end">
                                <flux:text class="text-sm font-medium">
                                    {{ formatMoney((max(0, (int) ($row['qty'] ?? 0)) * max(0, (float) ($row['unit_price'] ?? 0))), currentWorkspace()) }}
                                </flux:text>
                            </div>

                            <div class="flex items-end">
                                <flux:button type="button" icon="trash" size="sm" variant="danger" wire:click="removeDeliverable({{ $index }})"></flux:button>
                            </div>
                        </div>

                        @if(! empty($row['field_definitions']))
                            <div class="grid gap-3 md:grid-cols-2">
                                @foreach((array) $row['field_definitions'] as $field)
                                    @php
                                        $fieldKey = (string) data_get($field, 'key', '');
                                        $fieldType = (string) data_get($field, 'type', 'text');
                                        $fieldLabel = (string) data_get($field, 'label', $fieldKey);
                                    @endphp

                                    @if($fieldKey !== '')
                                        <flux:field wire:key="deliverable-{{ $row['id'] }}-field-{{ $fieldKey }}">
                                            <flux:label>{{ $fieldLabel }}</flux:label>

                                            @if($fieldType === 'select')
                                                <flux:select wire:model.blur="deliverables.{{ $index }}.fields.{{ $fieldKey }}">
                                                    <option value="">Select an option</option>
                                                    @foreach((array) data_get($field, 'options', []) as $optionValue)
                                                        <option value="{{ $optionValue }}">{{ $optionValue }}</option>
                                                    @endforeach
                                                </flux:select>
                                            @elseif($fieldType === 'number')
                                                <flux:input type="number" wire:model.blur="deliverables.{{ $index }}.fields.{{ $fieldKey }}" />
                                            @elseif($fieldType === 'date')
                                                <flux:input type="date" wire:model.blur="deliverables.{{ $index }}.fields.{{ $fieldKey }}" />
                                            @else
                                                <flux:input wire:model.blur="deliverables.{{ $index }}.fields.{{ $fieldKey }}" />
                                            @endif

                                            <flux:error name="deliverables.{{ $index }}.fields.{{ $fieldKey }}" />
                                        </flux:field>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-zinc-300 p-5 text-center text-sm text-zinc-500 dark:border-zinc-700">
                        Add deliverables by selecting a type, then set quantity, unit price, and optional custom parameters.
                    </div>
                @endforelse
            </div>

            <flux:error name="deliverables" class="mt-3" />

            <div class="mt-5 flex items-center justify-between border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <flux:text class="text-sm text-zinc-500">Total Budget</flux:text>
                <flux:heading size="lg">{{ formatMoney($this->totalBudget, currentWorkspace()) }}</flux:heading>
            </div>
        </section>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary">Create Campaign</flux:button>
        </div>
    </form>
</div>
