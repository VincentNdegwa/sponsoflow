<?php

use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app'), Title('Products')] class extends Component {
    
    #[Computed]
    public function products()
    {
        return Auth::user()->currentWorkspace()
            ->products()
            ->withCount('requirements', 'availableSlots')
            ->latest()
            ->get();
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
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($this->products as $product)
                    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm transition-shadow hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800">
                        <div class="p-6">
                            <div class="mb-4 flex items-start justify-between">
                                <div>
                                    <flux:heading size="lg" class="mb-1">{{ $product->name }}</flux:heading>
                                    <flux:badge variant="{{ $product->is_active ? 'lime' : 'zinc' }}">
                                        {{ $product->is_active ? 'Active' : 'Inactive' }}
                                    </flux:badge>
                                </div>
                                
                                <div class="text-right">
                                    <flux:text class="text-sm text-zinc-500">Base Price</flux:text>
                                    <flux:heading size="lg">${{ number_format($product->base_price, 2) }}</flux:heading>
                                </div>
                            </div>

                            @if($product->description)
                                <flux:text size="sm" class="mb-4 text-zinc-600 dark:text-zinc-400">
                                    {{ Str::limit($product->description, 100) }}
                                </flux:text>
                            @endif

                            <div class="mb-4 flex items-center gap-4 text-sm text-zinc-500">
                                <div class="flex items-center gap-1">
                                    <flux:icon.document-text variant="micro" />
                                    {{ $product->requirements_count }} requirements
                                </div>
                                <div class="flex items-center gap-1">
                                    <flux:icon.calendar variant="micro" />
                                    {{ $product->available_slots_count }} slots
                                </div>
                            </div>

                            <div class="flex gap-2">
                                <flux:button :href="route('products.show', $product)" variant="ghost" size="sm" class="flex-1">
                                    View Details
                                </flux:button>
                                <flux:button wire:click="deleteProduct({{ $product->id }})" variant="danger" size="sm">
                                    <flux:icon.trash variant="micro" />
                                </flux:button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
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