<?php

use App\Models\CampaignTemplate;
use App\Models\DeliverableOption;
use App\Support\CampaignFieldTypeRegistry;
use App\Services\CampaignCategoryService;
use App\Services\CampaignTemplateService;
use App\Services\DeliverableOptionService;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app'), Title('Campaign Templates')] class extends Component {
    use WithPagination;

    public bool $showFormModal = false;
    public bool $showCopyModal = false;
    public bool $showDeleteModal = false;
    public ?int $editingId = null;
    public ?int $copyTemplateId = null;
    public ?int $deleteTemplateId = null;

    public string $name = '';
    public ?int $categoryId = null;
    public array $briefFields = [];
    public array $briefFieldOptionsInput = [];
    public array $templateDeliverables = [];
    public string $mySearch = '';
    public string $starterSearch = '';

    public function mount(): void
    {
        $workspace = currentWorkspace();

        if (! $workspace || ! $workspace->isBrand()) {
            abort(403);
        }

        $this->addBriefField();
        $this->addTemplateDeliverable();
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showFormModal = true;
    }

    #[Computed]
    public function myTemplates(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $workspace = currentWorkspace();

        return CampaignTemplate::query()
            ->where('workspace_id', $workspace->id)
            ->with('category')
            ->when($this->mySearch !== '', function ($query): void {
                $searchTerm = '%'.$this->mySearch.'%';

                $query->where(function ($builder) use ($searchTerm): void {
                    $builder->where('name', 'like', $searchTerm)
                        ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', $searchTerm));
                });
            })
            ->orderBy('name')
            ->paginate(10, ['*'], 'myTemplatesPage');
    }

    #[Computed]
    public function starterTemplates(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return CampaignTemplate::query()
            ->whereNull('workspace_id')
            ->with('category')
            ->when($this->starterSearch !== '', function ($query): void {
                $searchTerm = '%'.$this->starterSearch.'%';

                $query->where(function ($builder) use ($searchTerm): void {
                    $builder->where('name', 'like', $searchTerm)
                        ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', $searchTerm));
                });
            })
            ->orderBy('name')
            ->paginate(10, ['*'], 'starterTemplatesPage');
    }

    public function updatedMySearch(): void
    {
        $this->resetPage('myTemplatesPage');
    }

    public function updatedStarterSearch(): void
    {
        $this->resetPage('starterTemplatesPage');
    }

    public function fieldTypeOptions(): array
    {
        return CampaignFieldTypeRegistry::selectOptions();
    }

    public function fieldTypeRequiresOptions(string $type): bool
    {
        return CampaignFieldTypeRegistry::requiresOptions($type);
    }

    #[Computed]
    public function categories(): \Illuminate\Support\Collection
    {
        return app(CampaignCategoryService::class)->visibleForWorkspace(currentWorkspace());
    }

    #[Computed]
    public function options(): \Illuminate\Support\Collection
    {
        return app(DeliverableOptionService::class)->visibleForWorkspace(currentWorkspace());
    }

    public function addBriefField(array $defaults = []): void
    {
        $index = count($this->briefFields);
        $label = (string) data_get($defaults, 'label', 'Question '.(count($this->briefFields) + 1));
        $key = (string) data_get($defaults, 'name', data_get($defaults, 'key', Str::slug($label, '_')));
        $options = array_values((array) data_get($defaults, 'options', []));

        $this->briefFields[] = [
            'key' => $key,
            'label' => $label,
            'type' => (string) data_get($defaults, 'type', 'text'),
            'options' => $options,
        ];

        $this->briefFieldOptionsInput[$index] = implode(', ', $options);
    }

    public function removeBriefField(int $index): void
    {
        if (! isset($this->briefFields[$index])) {
            return;
        }

        unset($this->briefFields[$index]);
        $this->briefFields = array_values($this->briefFields);
        unset($this->briefFieldOptionsInput[$index]);
        $this->briefFieldOptionsInput = array_values($this->briefFieldOptionsInput);
    }

    public function updatedBriefFieldOptionsInput(mixed $value, ?string $path = null): void
    {
        if ($path === null) {
            return;
        }

        $index = (int) Str::afterLast($path, '.');

        if (! isset($this->briefFields[$index])) {
            return;
        }

        $this->briefFields[$index]['options'] = $this->parseOptionsInput((string) $value);
    }

    public function addTemplateDeliverable(array $defaults = []): void
    {
        $optionId = (int) data_get($defaults, 'deliverable_option_id', $this->options->first()?->id);
        $option = $this->options->firstWhere('id', $optionId);

        if (! $option) {
            return;
        }

        $this->templateDeliverables[] = [
            'deliverable_option_id' => $option->id,
            'type_slug' => $option->slug,
            'label' => $option->name,
            'quantity' => max(1, (int) data_get($defaults, 'quantity', 1)),
            'unit_price' => max(0, (float) data_get($defaults, 'unit_price', 0)),
            'fields' => $this->hydrateDeliverableFields($option, (array) data_get($defaults, 'fields', [])),
        ];
    }

    public function removeTemplateDeliverable(int $index): void
    {
        if (! isset($this->templateDeliverables[$index])) {
            return;
        }

        unset($this->templateDeliverables[$index]);
        $this->templateDeliverables = array_values($this->templateDeliverables);
    }

    public function updatedTemplateDeliverables($value, mixed $path = null): void
    {
        if (! is_string($path) || $path === '' || ! str_ends_with($path, '.deliverable_option_id')) {
            return;
        }

        $parts = explode('.', $path);
        $index = (int) ($parts[1] ?? -1);

        if (! isset($this->templateDeliverables[$index])) {
            return;
        }

        $optionId = (int) ($this->templateDeliverables[$index]['deliverable_option_id'] ?? 0);
        $option = $this->options->firstWhere('id', $optionId);

        if (! $option) {
            return;
        }

        $this->templateDeliverables[$index]['type_slug'] = $option->slug;
        $this->templateDeliverables[$index]['label'] = $option->name;
        $this->templateDeliverables[$index]['fields'] = $this->hydrateDeliverableFields($option, (array) data_get($this->templateDeliverables[$index], 'fields', []));
    }

    public function createTemplate(): void
    {
        $this->syncBriefFieldOptions();
        $validated = $this->validate($this->rules());

        if (! $this->validateRequiredDeliverableFields()) {
            return;
        }

        app(CampaignTemplateService::class)->createWorkspaceTemplate(currentWorkspace(), $this->buildTemplatePayload($validated));

        $this->resetForm();
        $this->dispatch('success', 'Template created.');
    }

    public function startEdit(int $templateId): void
    {
        $template = CampaignTemplate::query()->findOrFail($templateId);

        if ((int) $template->workspace_id !== (int) currentWorkspace()->id) {
            return;
        }

        $this->editingId = $template->id;
        $this->name = $template->name;
        $this->categoryId = $template->category_id;
        $this->briefFields = [];
        $this->briefFieldOptionsInput = [];
        $this->templateDeliverables = [];

        foreach ((array) data_get($template->form_schema, 'sections.0.fields', []) as $field) {
            $this->addBriefField((array) $field);
        }

        foreach ((array) ($template->deliverable_options ?? []) as $deliverable) {
            $this->addTemplateDeliverable((array) $deliverable);
        }

        if ($this->briefFields === []) {
            $this->addBriefField();
        }

        if ($this->templateDeliverables === []) {
            $this->addTemplateDeliverable();
        }

        $this->showFormModal = true;
    }

    public function saveEdit(): void
    {
        if (! $this->editingId) {
            return;
        }

        $this->syncBriefFieldOptions();
        $validated = $this->validate($this->rules());

        if (! $this->validateRequiredDeliverableFields()) {
            return;
        }

        $template = CampaignTemplate::query()->findOrFail($this->editingId);

        app(CampaignTemplateService::class)->updateWorkspaceTemplate(currentWorkspace(), $template, $this->buildTemplatePayload($validated));

        $this->resetForm();
        $this->dispatch('success', 'Template updated.');
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    public function confirmCopyGlobal(int $templateId): void
    {
        $this->copyTemplateId = $templateId;
        $this->showCopyModal = true;
    }

    public function copyGlobalConfirmed(): void
    {
        if (! $this->copyTemplateId) {
            return;
        }

        $template = CampaignTemplate::query()->with('category')->findOrFail($this->copyTemplateId);

        app(CampaignTemplateService::class)->copyGlobalTemplate(currentWorkspace(), $template);

        $this->showCopyModal = false;
        $this->copyTemplateId = null;
        $this->dispatch('success', 'Template copied to your workspace.');
    }

    public function deleteTemplate(int $templateId): void
    {
        $this->deleteTemplateId = $templateId;
        $this->showDeleteModal = true;
    }

    public function deleteTemplateConfirmed(): void
    {
        if (! $this->deleteTemplateId) {
            return;
        }

        $templateId = $this->deleteTemplateId;
        $template = CampaignTemplate::query()->findOrFail($templateId);

        app(CampaignTemplateService::class)->deleteWorkspaceTemplate(currentWorkspace(), $template);

        if ($this->editingId === $templateId) {
            $this->resetForm();
        }

        $this->showDeleteModal = false;
        $this->deleteTemplateId = null;

        $this->dispatch('success', 'Template deleted.');
    }

    private function rules(): array
    {
        return [
            'name' => 'required|string|min:3|max:150',
            'categoryId' => 'nullable|integer|exists:categories,id',
            'briefFields' => 'required|array|min:1',
            'briefFields.*.label' => 'required|string|min:2|max:120',
            'briefFields.*.type' => 'required|string|'.CampaignFieldTypeRegistry::validationRule(),
            'briefFields.*.options' => 'nullable|array',
            'briefFields.*.options.*' => 'string|max:120',
            'templateDeliverables' => 'required|array|min:1',
            'templateDeliverables.*.deliverable_option_id' => 'required|integer|exists:deliverable_options,id',
            'templateDeliverables.*.quantity' => 'required|integer|min:1',
            'templateDeliverables.*.unit_price' => 'required|numeric|min:0',
            'templateDeliverables.*.fields' => 'nullable|array',
        ];
    }

    private function buildTemplatePayload(array $validated): array
    {
        $fields = collect($validated['briefFields'])
            ->map(function (array $field, int $index): array {
                $key = Str::slug((string) data_get($field, 'key', ''), '_');

                if ($key === '') {
                    $key = Str::slug((string) $field['label'], '_');
                }

                if ($key === '') {
                    $key = 'question_'.($index + 1);
                }

                return [
                    'name' => $key,
                    'label' => (string) $field['label'],
                    'type' => (string) $field['type'],
                    'options' => $this->fieldTypeRequiresOptions((string) $field['type'])
                        ? array_values((array) data_get($field, 'options', []))
                        : [],
                ];
            })
            ->values()
            ->all();

        $deliverables = collect($validated['templateDeliverables'])
            ->map(function (array $row): array {
                return [
                    'deliverable_option_id' => (int) $row['deliverable_option_id'],
                    'quantity' => max(1, (int) $row['quantity']),
                    'unit_price' => max(0, round((float) $row['unit_price'], 2)),
                    'fields' => array_merge(
                        [
                            'quantity' => max(1, (int) $row['quantity']),
                            'unit_price' => max(0, round((float) $row['unit_price'], 2)),
                        ],
                        (array) data_get($row, 'fields', []),
                    ),
                ];
            })
            ->values()
            ->all();

        return [
            'name' => $validated['name'],
            'category_id' => $validated['categoryId'],
            'deliverable_options' => $deliverables,
            'form_schema' => [
                'sections' => [
                    [
                        'title' => 'Campaign Details',
                        'fields' => $fields,
                    ],
                ],
            ],
        ];
    }

    private function resetForm(): void
    {
        $this->showFormModal = false;
        $this->editingId = null;
        $this->name = '';
        $this->categoryId = null;
        $this->briefFields = [];
        $this->briefFieldOptionsInput = [];
        $this->templateDeliverables = [];
        $this->addBriefField();
        $this->addTemplateDeliverable();
    }

    private function syncBriefFieldOptions(): void
    {
        foreach ($this->briefFields as $index => $field) {
            $this->briefFields[$index]['options'] = $this->parseOptionsInput((string) data_get($this->briefFieldOptionsInput, $index, ''));
        }
    }

    private function parseOptionsInput(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value)), fn (string $option): bool => $option !== ''));
    }

    private function hydrateDeliverableFields(DeliverableOption $option, array $existingFields = []): array
    {
        $hydrated = [];

        foreach ((array) ($option->fields ?? []) as $definition) {
            $fieldKey = (string) data_get($definition, 'key', '');

            if ($fieldKey === '') {
                continue;
            }

            $hydrated[$fieldKey] = data_get($existingFields, $fieldKey, data_get($definition, 'default', ''));
        }

        return $hydrated;
    }

    public function optionFieldDefinitions(int $index): array
    {
        $optionId = (int) data_get($this->templateDeliverables, $index.'.deliverable_option_id', 0);

        if ($optionId <= 0) {
            return [];
        }

        $option = $this->options->firstWhere('id', $optionId);

        if (! $option) {
            return [];
        }

        return (array) ($option->fields ?? []);
    }

    private function validateRequiredDeliverableFields(): bool
    {
        foreach ($this->templateDeliverables as $rowIndex => $row) {
            foreach ($this->optionFieldDefinitions($rowIndex) as $definition) {
                $fieldKey = (string) data_get($definition, 'key', '');

                if ($fieldKey === '' || ! (bool) data_get($definition, 'required', false)) {
                    continue;
                }

                $value = data_get($row, 'fields.'.$fieldKey);
                $isEmpty = is_array($value) ? count($value) === 0 : trim((string) $value) === '';

                if ($isEmpty) {
                    $fieldLabel = (string) data_get($definition, 'label', Str::headline($fieldKey));
                    $this->addError("templateDeliverables.$rowIndex.fields.$fieldKey", "{$fieldLabel} is required.");

                    return false;
                }
            }
        }

        return true;
    }
}; ?>

<div>
    <div class="mb-8 flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Campaign Templates</flux:heading>
            <flux:subheading>Create and manage your own templates, or copy from the starter library.</flux:subheading>
        </div>

        <x-campaigns.navigation current="templates" />
    </div>

    <div>
        <flux:heading size="lg" class="mb-4">Your Templates</flux:heading>

        <div class="mb-4 max-w-sm">
            <flux:input wire:model.live.debounce.300ms="mySearch" placeholder="Search your templates" />
        </div>

        @if($this->myTemplates->count() > 0)
            <flux:table class="mb-8" :paginate="$this->myTemplates">
                <flux:table.columns>
                    <flux:table.column>Name</flux:table.column>
                    <flux:table.column>Scope</flux:table.column>
                    <flux:table.column>Category</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach($this->myTemplates as $template)
                        <flux:table.row :key="$template->id">
                            <flux:table.cell>
                                <span class="font-medium text-zinc-800 dark:text-white">{{ $template->name }}</span>
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:badge size="sm" color="blue" inset="top bottom">Yours</flux:badge>
                            </flux:table.cell>

                            <flux:table.cell>
                                @if($template->category)
                                    <flux:badge size="sm" color="sky" inset="top bottom">{{ $template->category->name }}</flux:badge>
                                @else
                                    <span class="text-zinc-500">No category</span>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="flex justify-end">
                                    <flux:dropdown>
                                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                        <flux:menu>
                                            <flux:menu.item wire:click="startEdit({{ $template->id }})" icon="pencil-square">Edit</flux:menu.item>
                                            <flux:menu.separator />
                                            <flux:menu.item wire:click="deleteTemplate({{ $template->id }})" icon="trash" class="text-red-600">Delete</flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @else
            <div class="mb-6 rounded-xl border border-dashed border-zinc-300 p-6 text-center text-zinc-500 dark:border-zinc-700">
                You have not created a template yet.
            </div>

            <div class="mb-6 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900 dark:border-blue-800 dark:bg-blue-950/30 dark:text-blue-200">
                Need a quick start? Pick any template below and copy it into your own collection.
            </div>
        @endif

        <flux:heading size="lg" class="mb-4">Starter Library</flux:heading>

        <div class="mb-4 max-w-sm">
            <flux:input wire:model.live.debounce.300ms="starterSearch" placeholder="Search starter templates" />
        </div>

        @if($this->starterTemplates->count() > 0)
            <flux:table :paginate="$this->starterTemplates">
                <flux:table.columns>
                    <flux:table.column>Name</flux:table.column>
                    <flux:table.column>Scope</flux:table.column>
                    <flux:table.column>Category</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach($this->starterTemplates as $template)
                        <flux:table.row :key="$template->id">
                            <flux:table.cell>
                                <span class="font-medium text-zinc-800 dark:text-white">{{ $template->name }}</span>
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:badge size="sm" color="zinc" inset="top bottom">Starter</flux:badge>
                            </flux:table.cell>

                            <flux:table.cell>
                                @if($template->category)
                                    <flux:badge size="sm" color="sky" inset="top bottom">{{ $template->category->name }}</flux:badge>
                                @else
                                    <span class="text-zinc-500">No category</span>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="flex justify-end">
                                    <flux:dropdown>
                                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                        <flux:menu>
                                            <flux:menu.item wire:click="confirmCopyGlobal({{ $template->id }})" icon="document-duplicate">Copy</flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @else
            <div class="rounded-xl border border-dashed border-zinc-300 p-6 text-center text-zinc-500 dark:border-zinc-700">
                Starter templates are not available yet.
            </div>
        @endif
    </div>

    <flux:modal wire:model.self="showFormModal" flyout :dismissible="false" class="md:w-5xl">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Edit Template' : 'Create Template' }}</flux:heading>
                <flux:text class="mt-2">Build your template brief and default deliverables in one place.</flux:text>
            </div>

            <div class="grid gap-3 md:grid-cols-2">
                <flux:field>
                    <flux:label>Template Name</flux:label>
                    <flux:input wire:model.blur="name" placeholder="Q2 Launch Brief" />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>Category</flux:label>
                    <flux:select wire:model="categoryId">
                        <option value="">No category</option>
                        @foreach($this->categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="categoryId" />
                </flux:field>
            </div>

            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="mb-3 flex items-center justify-between">
                    <flux:heading size="sm">Brief Questions</flux:heading>
                    <flux:button size="sm" variant="ghost" icon="plus" wire:click="addBriefField">Add Question</flux:button>
                </div>

                <div class="space-y-3 max-h-80 overflow-y-auto pr-1">
                    @foreach($briefFields as $index => $field)
                        <div class="grid gap-3 rounded-xl border border-zinc-200 p-4 md:grid-cols-[1.7fr_1fr_auto] dark:border-zinc-700" wire:key="template-brief-field-{{ $index }}">
                            <flux:field>
                                <flux:label>Question Label</flux:label>
                                <flux:input wire:model.blur="briefFields.{{ $index }}.label" />
                                <flux:error name="briefFields.{{ $index }}.label" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Type</flux:label>
                                <flux:select wire:model.live="briefFields.{{ $index }}.type">
                                    @foreach($this->fieldTypeOptions() as $type)
                                        <option value="{{ $type['value'] }}">{{ $type['label'] }}</option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="briefFields.{{ $index }}.type" />
                            </flux:field>

                            <div class="flex items-end">
                                <flux:button type="button" size="sm" variant="danger" icon="trash" wire:click="removeBriefField({{ $index }})"></flux:button>
                            </div>

                            @if($this->fieldTypeRequiresOptions((string) ($field['type'] ?? 'text')))
                                <div class="md:col-span-4">
                                    <flux:field>
                                        <flux:label>Select Options (comma separated)</flux:label>
                                        <flux:input wire:model.blur="briefFieldOptionsInput.{{ $index }}" />
                                    </flux:field>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="mb-3 flex items-center justify-between">
                    <flux:heading size="sm">Default Deliverables</flux:heading>
                    <flux:button size="sm" variant="ghost" icon="plus" wire:click="addTemplateDeliverable">Add Deliverable</flux:button>
                </div>

                <div class="space-y-3 max-h-80 overflow-y-auto pr-1">
                    @foreach($templateDeliverables as $index => $row)
                        <div class="grid gap-3 rounded-xl border border-zinc-200 p-4 md:grid-cols-[1.5fr_0.8fr_0.9fr_auto] dark:border-zinc-700" wire:key="template-deliverable-{{ $index }}">
                            <flux:field>
                                <flux:label>Deliverable Type</flux:label>
                                <flux:select wire:model.change="templateDeliverables.{{ $index }}.deliverable_option_id">
                                    @foreach($this->options as $option)
                                        <option value="{{ $option->id }}">{{ $option->name }}</option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="templateDeliverables.{{ $index }}.deliverable_option_id" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Quantity</flux:label>
                                <flux:input type="number" min="1" wire:model.live="templateDeliverables.{{ $index }}.quantity" />
                                <flux:error name="templateDeliverables.{{ $index }}.quantity" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Unit Price</flux:label>
                                <flux:input type="number" min="0" step="0.01" wire:model.live="templateDeliverables.{{ $index }}.unit_price" />
                                <flux:error name="templateDeliverables.{{ $index }}.unit_price" />
                            </flux:field>

                            <div class="flex items-end">
                                <flux:button type="button" size="sm" variant="danger" icon="trash" wire:click="removeTemplateDeliverable({{ $index }})"></flux:button>
                            </div>

                            @php
                                $fieldDefinitions = $this->optionFieldDefinitions($index);
                            @endphp
                            @if(count($fieldDefinitions) > 0)
                                <div class="md:col-span-4 grid gap-3 md:grid-cols-2">
                                    @foreach($fieldDefinitions as $fieldDefinition)
                                        @php
                                            $fieldKey = (string) data_get($fieldDefinition, 'key', '');
                                            $fieldLabel = (string) data_get($fieldDefinition, 'label', Str::headline($fieldKey));
                                            $fieldType = (string) data_get($fieldDefinition, 'type', 'text');
                                            $fieldOptions = (array) data_get($fieldDefinition, 'options', []);
                                        @endphp

                                        @if($fieldKey !== '')
                                            <flux:field>
                                                <flux:label>
                                                    {{ $fieldLabel }}
                                                    @if((bool) data_get($fieldDefinition, 'required', false)) * @endif
                                                </flux:label>

                                                @if($fieldType === 'textarea')
                                                    <flux:textarea wire:model.blur="templateDeliverables.{{ $index }}.fields.{{ $fieldKey }}" />
                                                @elseif($fieldType === 'select')
                                                    <flux:select wire:model="templateDeliverables.{{ $index }}.fields.{{ $fieldKey }}">
                                                        <option value="">Select an option</option>
                                                        @foreach($fieldOptions as $choice)
                                                            <option value="{{ $choice }}">{{ $choice }}</option>
                                                        @endforeach
                                                    </flux:select>
                                                @elseif($fieldType === 'number')
                                                    <flux:input type="number" wire:model.live="templateDeliverables.{{ $index }}.fields.{{ $fieldKey }}" />
                                                @elseif($fieldType === 'date')
                                                    <flux:input type="date" wire:model="templateDeliverables.{{ $index }}.fields.{{ $fieldKey }}" />
                                                @else
                                                    <flux:input wire:model.blur="templateDeliverables.{{ $index }}.fields.{{ $fieldKey }}" />
                                                @endif

                                                <flux:error name="templateDeliverables.{{ $index }}.fields.{{ $fieldKey }}" />
                                            </flux:field>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex gap-3">
                <flux:spacer />
                <flux:button variant="ghost" wire:click="cancelEdit">Cancel</flux:button>
                @if($editingId)
                    <flux:button variant="primary" wire:click="saveEdit">Save Template</flux:button>
                @else
                    <flux:button variant="primary" wire:click="createTemplate">Create Template</flux:button>
                @endif
            </div>
        </div>
    </flux:modal>

    <x-campaigns.copy-confirm-modal
        model="showCopyModal"
        title="Copy global template?"
        message="This creates your own editable copy in your collection."
        confirm-action="copyGlobalConfirmed"
        confirm-label="Copy Template"
    />

    <x-campaigns.delete-confirm-modal
        model="showDeleteModal"
        title="Delete template?"
        message="This template will be removed from your workspace."
        confirm-action="deleteTemplateConfirmed"
        confirm-label="Delete Template"
    />
</div>
