<?php

use App\Models\DeliverableOption;
use App\Support\CampaignFieldTypeRegistry;
use App\Services\DeliverableOptionService;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app'), Title('Deliverable Options')] class extends Component {
    use WithPagination;

    public bool $showFormModal = false;
    public bool $showCopyModal = false;
    public bool $showDeleteModal = false;
    public ?int $editingId = null;
    public ?int $copyOptionId = null;
    public ?int $deleteOptionId = null;

    public string $name = '';
    public bool $isActive = true;
    public array $fields = [];
    public array $fieldOptionsInput = [];
    public string $mySearch = '';
    public string $starterSearch = '';
    public string $myStatusFilter = 'all';
    public string $starterStatusFilter = 'all';

    public function mount(): void
    {
        $workspace = currentWorkspace();

        if (! $workspace || ! $workspace->isBrand()) {
            abort(403);
        }

        $this->addField();
    }

    #[Computed]
    public function myOptions(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $workspace = currentWorkspace();

        return DeliverableOption::query()
            ->where('workspace_id', $workspace->id)
            ->when($this->mySearch !== '', fn ($query) => $query->where('name', 'like', '%'.$this->mySearch.'%'))
            ->when($this->myStatusFilter !== 'all', fn ($query) => $query->where('is_active', $this->myStatusFilter === 'active'))
            ->orderBy('name')
            ->paginate(10, ['*'], 'myOptionsPage');
    }

    #[Computed]
    public function starterOptions(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return DeliverableOption::query()
            ->whereNull('workspace_id')
            ->when($this->starterSearch !== '', fn ($query) => $query->where('name', 'like', '%'.$this->starterSearch.'%'))
            ->when($this->starterStatusFilter !== 'all', fn ($query) => $query->where('is_active', $this->starterStatusFilter === 'active'))
            ->orderBy('name')
            ->paginate(10, ['*'], 'starterOptionsPage');
    }

    public function updatedMySearch(): void
    {
        $this->resetPage('myOptionsPage');
    }

    public function updatedStarterSearch(): void
    {
        $this->resetPage('starterOptionsPage');
    }

    public function updatedMyStatusFilter(): void
    {
        $this->resetPage('myOptionsPage');
    }

    public function updatedStarterStatusFilter(): void
    {
        $this->resetPage('starterOptionsPage');
    }

    public function fieldTypeOptions(): array
    {
        return CampaignFieldTypeRegistry::selectOptions();
    }

    public function fieldTypeRequiresOptions(string $type): bool
    {
        return CampaignFieldTypeRegistry::requiresOptions($type);
    }

    public function addField(array $defaults = []): void
    {
        $index = count($this->fields);
        $label = (string) data_get($defaults, 'label', 'Field '.(count($this->fields) + 1));
        $key = (string) data_get($defaults, 'key', Str::slug($label, '_'));

        if ($key === '') {
            $key = 'field_'.Str::lower(Str::random(10));
        }

        $this->fields[] = [
            'key' => $key,
            'label' => $label,
            'type' => (string) data_get($defaults, 'type', 'text'),
            'required' => (bool) data_get($defaults, 'required', false),
            'options' => array_values((array) data_get($defaults, 'options', [])),
        ];

        $this->fieldOptionsInput[$index] = implode(', ', (array) data_get($defaults, 'options', []));
    }

    public function removeField(int $index): void
    {
        if (! isset($this->fields[$index])) {
            return;
        }

        unset($this->fields[$index]);
        $this->fields = array_values($this->fields);
        unset($this->fieldOptionsInput[$index]);
        $this->fieldOptionsInput = array_values($this->fieldOptionsInput);
    }

    public function openCreateModal(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->isActive = true;
        $this->fields = [];
        $this->fieldOptionsInput = [];
        $this->addField();
        $this->showFormModal = true;
    }

    public function updatedFieldOptionsInput(mixed $value, ?string $path = null): void
    {
        if ($path === null) {
            return;
        }

        $index = (int) Str::afterLast($path, '.');

        if (! isset($this->fields[$index])) {
            return;
        }

        $this->fields[$index]['options'] = $this->parseOptionsInput((string) $value);
    }

    public function saveOption(): void
    {
        $this->syncFieldOptions();
        $validated = $this->validate($this->rules());

        foreach ((array) data_get($validated, 'fields', []) as $index => $field) {
            if (! $this->fieldTypeRequiresOptions((string) data_get($field, 'type', 'text'))) {
                continue;
            }

            if (count((array) data_get($field, 'options', [])) === 0) {
                $this->addError("fields.$index.options", 'Please provide comma-separated options for select fields.');

                return;
            }
        }

        if ($this->editingId) {
            $option = DeliverableOption::query()->findOrFail($this->editingId);

            app(DeliverableOptionService::class)->updateWorkspaceOption(currentWorkspace(), $option, [
                'name' => $validated['name'],
                'is_active' => $validated['isActive'],
                'fields' => $validated['fields'],
            ]);

            $this->resetForm();
            $this->dispatch('success', 'Deliverable option updated.');

            return;
        }

        app(DeliverableOptionService::class)->createWorkspaceOption(currentWorkspace(), [
            'name' => $validated['name'],
            'is_active' => $validated['isActive'],
            'fields' => $validated['fields'],
        ]);

        $this->resetForm();
        $this->dispatch('success', 'Deliverable option created.');
    }

    public function startEdit(int $optionId): void
    {
        $option = DeliverableOption::query()->findOrFail($optionId);

        if ((int) $option->workspace_id !== (int) currentWorkspace()->id) {
            return;
        }

        $this->editingId = $option->id;
        $this->name = $option->name;
        $this->isActive = (bool) $option->is_active;
        $this->fields = [];
        $this->fieldOptionsInput = [];

        foreach ((array) ($option->fields ?? []) as $field) {
            $this->addField($field);
        }

        if ($this->fields === []) {
            $this->addField();
        }

        $this->showFormModal = true;
    }

    public function closeFormModal(): void
    {
        $this->resetForm();
    }

    public function confirmCopyGlobal(int $optionId): void
    {
        $this->copyOptionId = $optionId;
        $this->showCopyModal = true;
    }

    public function copyGlobalConfirmed(): void
    {
        if (! $this->copyOptionId) {
            return;
        }

        $option = DeliverableOption::query()->findOrFail($this->copyOptionId);

        app(DeliverableOptionService::class)->copyGlobalOption(currentWorkspace(), $option);

        $this->showCopyModal = false;
        $this->copyOptionId = null;
        $this->dispatch('success', 'Deliverable option copied.');
    }

    public function deleteOption(int $optionId): void
    {
        $this->deleteOptionId = $optionId;
        $this->showDeleteModal = true;
    }

    public function deleteOptionConfirmed(): void
    {
        if (! $this->deleteOptionId) {
            return;
        }

        $optionId = $this->deleteOptionId;
        $option = DeliverableOption::query()->findOrFail($optionId);

        app(DeliverableOptionService::class)->deleteWorkspaceOption(currentWorkspace(), $option);

        if ($this->editingId === $optionId) {
            $this->resetForm();
        }

        $this->showDeleteModal = false;
        $this->deleteOptionId = null;

        $this->dispatch('success', 'Deliverable option deleted.');
    }

    private function resetForm(): void
    {
        $this->showFormModal = false;
        $this->editingId = null;
        $this->name = '';
        $this->isActive = true;
        $this->fields = [];
        $this->fieldOptionsInput = [];
        $this->addField();
    }

    private function syncFieldOptions(): void
    {
        foreach ($this->fields as $index => $field) {
            $this->fields[$index]['options'] = $this->parseOptionsInput((string) data_get($this->fieldOptionsInput, $index, ''));
        }
    }

    private function parseOptionsInput(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value)), fn (string $option): bool => $option !== ''));
    }

    private function rules(): array
    {
        return [
            'name' => 'required|string|min:2|max:120',
            'isActive' => 'boolean',
            'fields' => 'nullable|array',
            'fields.*.label' => 'required|string|min:2|max:120',
            'fields.*.type' => 'required|string|'.CampaignFieldTypeRegistry::validationRule(),
            'fields.*.required' => 'boolean',
            'fields.*.options' => 'nullable|array',
            'fields.*.options.*' => 'string|max:120',
        ];
    }
}; ?>

<div>
    <div class="mb-8 flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Deliverable Types</flux:heading>
            <flux:subheading>Create your own deliverable types or copy from the starter library.</flux:subheading>
        </div>

        <x-campaigns.navigation current="deliverable-options" />
    </div>

    <div>
        <flux:heading size="lg" class="mb-4">Your Deliverable Types</flux:heading>

        <div class="mb-4 grid gap-3 md:grid-cols-2">
            <flux:input wire:model.live.debounce.300ms="mySearch" placeholder="Search your deliverable types" />
            <flux:select wire:model.live="myStatusFilter">
                <option value="all">All Statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </flux:select>
        </div>

        @if($this->myOptions->count() > 0)
            <flux:table class="mb-8" :paginate="$this->myOptions">
                <flux:table.columns>
                    <flux:table.column>Name</flux:table.column>
                    <flux:table.column>Scope</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach($this->myOptions as $option)
                        <flux:table.row :key="$option->id">
                            <flux:table.cell>
                                <span class="font-medium text-zinc-800 dark:text-white">{{ $option->name }}</span>
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:badge size="sm" color="blue" inset="top bottom">Yours</flux:badge>
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:badge size="sm" :color="$option->is_active ? 'lime' : 'zinc'" inset="top bottom">
                                    {{ $option->is_active ? 'Active' : 'Inactive' }}
                                </flux:badge>
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="flex justify-end">
                                    <flux:dropdown>
                                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                        <flux:menu>
                                            <flux:menu.item wire:click="startEdit({{ $option->id }})" icon="pencil-square">Edit</flux:menu.item>
                                            <flux:menu.separator />
                                            <flux:menu.item wire:click="deleteOption({{ $option->id }})" icon="trash" class="text-red-600">Delete</flux:menu.item>
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
                You have not created a deliverable type yet.
            </div>

            <div class="mb-6 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900 dark:border-blue-800 dark:bg-blue-950/30 dark:text-blue-200">
                You can copy a starter type below and customize it.
            </div>
        @endif

        <flux:heading size="lg" class="mb-4">Starter Library</flux:heading>

        <div class="mb-4 grid gap-3 md:grid-cols-2">
            <flux:input wire:model.live.debounce.300ms="starterSearch" placeholder="Search starter deliverable types" />
            <flux:select wire:model.live="starterStatusFilter">
                <option value="all">All Statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </flux:select>
        </div>

        @if($this->starterOptions->count() > 0)
            <flux:table :paginate="$this->starterOptions">
                <flux:table.columns>
                    <flux:table.column>Name</flux:table.column>
                    <flux:table.column>Scope</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach($this->starterOptions as $option)
                        <flux:table.row :key="$option->id">
                            <flux:table.cell>
                                <span class="font-medium text-zinc-800 dark:text-white">{{ $option->name }}</span>
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:badge size="sm" color="zinc" inset="top bottom">Starter</flux:badge>
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:badge size="sm" :color="$option->is_active ? 'lime' : 'zinc'" inset="top bottom">
                                    {{ $option->is_active ? 'Active' : 'Inactive' }}
                                </flux:badge>
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="flex justify-end">
                                    <flux:dropdown>
                                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                        <flux:menu>
                                            <flux:menu.item wire:click="confirmCopyGlobal({{ $option->id }})" icon="document-duplicate">Copy</flux:menu.item>
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
                Starter deliverable types are not available yet.
            </div>
        @endif
    </div>

    <flux:modal wire:model.self="showFormModal" flyout :dismissible="false" class="md:w-5xl">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Edit Deliverable Option' : 'Create Deliverable Option' }}</flux:heading>
                <flux:text class="mt-2">Set up the field details your team needs for this deliverable type.</flux:text>
            </div>

            <div class="grid gap-3 md:grid-cols-2">
                <flux:field>
                    <flux:label>Name</flux:label>
                    <flux:input wire:model.blur="name" placeholder="Instagram Reel" />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>Active</flux:label>
                    <div class="mt-2">
                        <flux:switch wire:model="isActive" />
                    </div>
                </flux:field>
            </div>

            <div>
                <div class="mb-3 flex items-center justify-between">
                    <flux:heading size="sm">Custom Fields</flux:heading>
                    <flux:button size="sm" variant="ghost" icon="plus" wire:click="addField">Add Field</flux:button>
                </div>

                <div class="space-y-3 max-h-88 overflow-y-auto pr-1">
                    @foreach($fields as $index => $field)
                        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" wire:key="option-field-{{ $index }}">
                            <div class="grid gap-3 md:grid-cols-[1fr_0.8fr_0.8fr_auto]">
                                <flux:field>
                                    <flux:label>Label</flux:label>
                                    <flux:input wire:model.blur="fields.{{ $index }}.label" placeholder="Duration (seconds)" />
                                    <flux:error name="fields.{{ $index }}.label" />
                                </flux:field>

                                <flux:field>
                                    <flux:label>Type</flux:label>
                                    <flux:select wire:model.live="fields.{{ $index }}.type">
                                        @foreach($this->fieldTypeOptions() as $type)
                                            <option value="{{ $type['value'] }}">{{ $type['label'] }}</option>
                                        @endforeach
                                    </flux:select>
                                </flux:field>

                                <flux:field>
                                    <flux:label>Required</flux:label>
                                    <div class="mt-2">
                                        <flux:switch wire:model="fields.{{ $index }}.required" />
                                    </div>
                                </flux:field>

                                <div class="flex items-end">
                                    <flux:button type="button" size="sm" variant="danger" icon="trash" wire:click="removeField({{ $index }})"></flux:button>
                                </div>
                            </div>

                            @if($this->fieldTypeRequiresOptions((string) ($field['type'] ?? 'text')))
                                <div class="mt-3">
                                    <flux:field>
                                        <flux:label>Select Options (comma separated)</flux:label>
                                        <flux:input wire:model.blur="fieldOptionsInput.{{ $index }}" placeholder="Organic, Paid Ads, Whitelisting" />
                                        <flux:error name="fields.{{ $index }}.options" />
                                    </flux:field>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex gap-3">
                <flux:spacer />
                <flux:button variant="ghost" wire:click="closeFormModal">Cancel</flux:button>
                <flux:button variant="primary" wire:click="saveOption">{{ $editingId ? 'Save Option' : 'Create Option' }}</flux:button>
            </div>
        </div>
    </flux:modal>

    <x-campaigns.copy-confirm-modal
        model="showCopyModal"
        title="Copy global deliverable type?"
        message="This creates your own editable copy in your collection."
        confirm-action="copyGlobalConfirmed"
        confirm-label="Copy Type"
    />

    <x-campaigns.delete-confirm-modal
        model="showDeleteModal"
        title="Delete deliverable type?"
        message="This deliverable type will be removed from your workspace."
        confirm-action="deleteOptionConfirmed"
        confirm-label="Delete Type"
    />
</div>
