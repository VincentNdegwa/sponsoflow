<?php

use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app'), Title('Products')] class extends Component {
    use WithPagination;

    public $sortBy = 'created_at';
    public $sortDirection = 'desc';

    public function sort($column)
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    #[Computed]
    public function products()
    {
        return Auth::user()->currentWorkspace()
            ->products()
            ->withCount(['requirements', 'availableSlots'])
            ->tap(fn ($query) => $this->sortBy ? $query->orderBy($this->sortBy, $this->sortDirection) : $query)
            ->paginate(10);
    }

    public function deleteProduct(Product $product): void
    {
        $product->delete();
        
        $this->dispatch('product-deleted');
    }
}; ?>

<div>
    <div class="mb-8 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Products</flux:heading>
            <flux:subheading>Build and manage your sponsorship offerings</flux:subheading>
        </div>
        
        <flux:button :href="route('products.create')" variant="primary" icon="plus">
            Create Product
        </flux:button>
    </div>

    @if($this->products->count() > 0)
        <flux:table :paginate="$this->products">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">Name</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'is_active'" :direction="$sortDirection" wire:click="sort('is_active')">Status</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'base_price'" :direction="$sortDirection" wire:click="sort('base_price')">Price</flux:table.column>
                <flux:table.column>Stats</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->products as $product)
                    <flux:table.row :key="$product->id">
                        <flux:table.cell>
                            <div class="flex flex-col">
                                <span class="font-medium text-zinc-800 dark:text-white">{{ $product->name }}</span>
                                @if($product->description)
                                    <span class="text-xs text-zinc-500 line-clamp-1">{{ Str::limit($product->description, 60) }}</span>
                                @endif
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge size="sm" :color="$product->is_active ? 'lime' : 'zinc'" inset="top bottom">
                                {{ $product->is_active ? 'Active' : 'Inactive' }}
                            </flux:badge>
                        </flux:table.cell>

                        <flux:table.cell variant="strong">
                            ${{ number_format($product->base_price, 2) }}
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="flex gap-3 text-xs text-zinc-500">
                                <span title="Requirements" class="flex items-center gap-1">
                                    <flux:icon.document-text variant="micro" /> {{ $product->requirements_count }}
                                </span>
                                <span title="Available Slots" class="flex items-center gap-1">
                                    <flux:icon.calendar variant="micro" /> {{ $product->available_slots_count }}
                                </span>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="flex justify-end gap-2">                                
                                <flux:dropdown>
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                    <flux:menu>
                                        <flux:menu.item :href="route('products.show', $product)" icon="eye">View</flux:menu.item>
                                        <flux:menu.item :href="route('products.edit', $product)" icon="pencil">Edit</flux:menu.item>
                                        <flux:menu.item wire:click="deleteProduct({{ $product->id }})" variant="danger" icon="trash">Delete</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @else
        <div class="rounded-lg border-2 border-dashed border-zinc-300 p-12 text-center dark:border-zinc-600">
            <flux:icon.cube class="mx-auto h-12 w-12 text-zinc-400" />
            <flux:heading size="lg" class="mt-4">No products yet</flux:heading>
            <flux:text class="mt-2 text-zinc-600 dark:text-zinc-400">
                Create your first product to start accepting sponsorships
            </flux:text>
            <flux:button :href="route('products.create')" variant="primary" class="mt-4" icon="plus">
                Create Your First Product
            </flux:button>
        </div>
    @endif
</div>