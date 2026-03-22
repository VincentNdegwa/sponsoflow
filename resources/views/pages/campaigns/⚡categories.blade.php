<?php

use App\Models\Category;
use App\Services\CampaignCategoryService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app'), Title('Campaign Categories')] class extends Component {
    use WithPagination;

    public bool $showFormModal = false;
    public bool $showCopyModal = false;
    public bool $showDeleteModal = false;
    public ?int $editingId = null;
    public ?int $copyCategoryId = null;
    public ?int $deleteCategoryId = null;
    public string $formName = '';
    public string $mySearch = '';
    public string $starterSearch = '';

    public function mount(): void
    {
        $workspace = currentWorkspace();

        if (! $workspace || ! $workspace->isBrand()) {
            abort(403);
        }
    }

    #[Computed]
    public function myCategories(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $workspace = currentWorkspace();

        return Category::query()
            ->where('workspace_id', $workspace->id)
            ->when($this->mySearch !== '', fn ($query) => $query->where('name', 'like', '%'.$this->mySearch.'%'))
            ->orderBy('name')
            ->paginate(10, ['*'], 'myCategoriesPage');
    }

    #[Computed]
    public function starterCategories(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return Category::query()
            ->whereNull('workspace_id')
            ->when($this->starterSearch !== '', fn ($query) => $query->where('name', 'like', '%'.$this->starterSearch.'%'))
            ->orderBy('name')
            ->paginate(10, ['*'], 'starterCategoriesPage');
    }

    public function updatedMySearch(): void
    {
        $this->resetPage('myCategoriesPage');
    }

    public function updatedStarterSearch(): void
    {
        $this->resetPage('starterCategoriesPage');
    }

    public function openCreateModal(): void
    {
        $this->editingId = null;
        $this->formName = '';
        $this->showFormModal = true;
    }

    public function startEdit(int $categoryId): void
    {
        $category = Category::query()->findOrFail($categoryId);

        if ((int) $category->workspace_id !== (int) currentWorkspace()->id) {
            return;
        }

        $this->editingId = $category->id;
        $this->formName = $category->name;
        $this->showFormModal = true;
    }

    public function saveCategory(): void
    {
        $validated = $this->validate([
            'formName' => 'required|string|min:2|max:120',
        ]);

        if ($this->editingId) {
            $category = Category::query()->findOrFail($this->editingId);

            app(CampaignCategoryService::class)->updateWorkspaceCategory(currentWorkspace(), $category, $validated['formName']);

            $this->dispatch('success', 'Category updated.');
            $this->closeFormModal();

            return;
        }

        app(CampaignCategoryService::class)->createWorkspaceCategory(currentWorkspace(), $validated['formName']);

        $this->dispatch('success', 'Category created.');
        $this->closeFormModal();
    }

    public function closeFormModal(): void
    {
        $this->showFormModal = false;
        $this->editingId = null;
        $this->formName = '';
    }

    public function confirmCopyGlobal(int $categoryId): void
    {
        $this->copyCategoryId = $categoryId;
        $this->showCopyModal = true;
    }

    public function copyGlobalConfirmed(): void
    {
        if (! $this->copyCategoryId) {
            return;
        }

        $category = Category::query()->findOrFail($this->copyCategoryId);

        app(CampaignCategoryService::class)->copyGlobalCategory(currentWorkspace(), $category);

        $this->showCopyModal = false;
        $this->copyCategoryId = null;
        $this->dispatch('success', 'Category copied to your workspace.');
    }

    public function deleteCategory(int $categoryId): void
    {
        $this->deleteCategoryId = $categoryId;
        $this->showDeleteModal = true;
    }

    public function deleteCategoryConfirmed(): void
    {
        if (! $this->deleteCategoryId) {
            return;
        }

        $categoryId = $this->deleteCategoryId;
        $category = Category::query()->findOrFail($categoryId);

        app(CampaignCategoryService::class)->deleteWorkspaceCategory(currentWorkspace(), $category);

        if ($this->editingId === $categoryId) {
            $this->closeFormModal();
        }

        $this->showDeleteModal = false;
        $this->deleteCategoryId = null;

        $this->dispatch('success', 'Category deleted.');
    }
}; ?>

<div>
    <div class="mb-8 flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Campaign Categories</flux:heading>
            <flux:subheading>Organize your campaigns with your own categories or copy from the starter library.</flux:subheading>
        </div>

        <x-campaigns.navigation current="categories" />
    </div>

    <div>
        <flux:heading size="lg" class="mb-4">Your Categories</flux:heading>

        <div class="mb-4 max-w-sm">
            <flux:input wire:model.live.debounce.300ms="mySearch" placeholder="Search your categories" />
        </div>

        @if($this->myCategories->count() > 0)
            <flux:table class="mb-8" :paginate="$this->myCategories">
                <flux:table.columns>
                    <flux:table.column>Name</flux:table.column>
                    <flux:table.column>Scope</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach($this->myCategories as $category)
                        <flux:table.row :key="$category->id">
                            <flux:table.cell>
                                <span class="font-medium text-zinc-800 dark:text-white">{{ $category->name }}</span>
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:badge size="sm" color="blue" inset="top bottom">Yours</flux:badge>
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="flex justify-end">
                                    <flux:dropdown>
                                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                        <flux:menu>
                                            <flux:menu.item wire:click="startEdit({{ $category->id }})" icon="pencil-square">Edit</flux:menu.item>
                                            <flux:menu.separator />
                                            <flux:menu.item wire:click="deleteCategory({{ $category->id }})" icon="trash" class="text-red-600">Delete</flux:menu.item>
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
                You have not added a category yet.
            </div>

    
        @endif

        <flux:heading size="lg" class="mb-4">Starter Library</flux:heading>

        <div class="mb-4 max-w-sm">
            <flux:input wire:model.live.debounce.300ms="starterSearch" placeholder="Search starter categories" />
        </div>

        @if($this->starterCategories->count() > 0)
            <flux:table :paginate="$this->starterCategories">
                <flux:table.columns>
                    <flux:table.column>Name</flux:table.column>
                    <flux:table.column>Scope</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach($this->starterCategories as $category)
                        <flux:table.row :key="$category->id">
                            <flux:table.cell>
                                <span class="font-medium text-zinc-800 dark:text-white">{{ $category->name }}</span>
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:badge size="sm" color="zinc" inset="top bottom">Starter</flux:badge>
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="flex justify-end">
                                    <flux:dropdown>
                                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                        <flux:menu>
                                            <flux:menu.item wire:click="confirmCopyGlobal({{ $category->id }})" icon="document-duplicate">Copy</flux:menu.item>
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
                Starter categories are not available yet.
            </div>
        @endif
    </div>

    <flux:modal wire:model.self="showFormModal" flyout :dismissible="false" class="md:w-5xl">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Edit Category' : 'Create Category' }}</flux:heading>
                <flux:text class="mt-2">Categories are scoped to your workspace once created.</flux:text>
            </div>

            <flux:field>
                <flux:label>Name</flux:label>
                <flux:input wire:model.blur="formName" placeholder="Video Content" />
                <flux:error name="formName" />
            </flux:field>

            <div class="flex gap-3">
                <flux:spacer />
                <flux:button variant="ghost" wire:click="closeFormModal">Cancel</flux:button>
                <flux:button variant="primary" wire:click="saveCategory">{{ $editingId ? 'Save' : 'Create' }}</flux:button>
            </div>
        </div>
    </flux:modal>

    <x-campaigns.copy-confirm-modal
        model="showCopyModal"
        title="Copy global category?"
        message="This creates your own editable copy in your collection."
        confirm-action="copyGlobalConfirmed"
        confirm-label="Copy Category"
    />

    <x-campaigns.delete-confirm-modal
        model="showDeleteModal"
        title="Delete category?"
        message="This category will be removed from your workspace."
        confirm-action="deleteCategoryConfirmed"
        confirm-label="Delete Category"
    />
</div>
