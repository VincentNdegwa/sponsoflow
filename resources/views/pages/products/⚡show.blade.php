<?php

use App\Models\Product;
use App\Models\Slot;
use App\Enums\SlotStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app'), Title('Product Details')] class extends Component {
    
    public Product $product;

    public function mount(Product $product): void
    {
        if ($product->workspace_id !== Auth::user()->currentWorkspace()->id) {
            abort(404);
        }
        $this->product = $product->load('requirements', 'slots');
    }

    public function createSlot(): void
    {
        $slot = $this->product->slots()->create([
            'workspace_id' => Auth::user()->currentWorkspace()->id,
            'slot_date' => now()->addWeek(),
            'price' => $this->product->base_price,
            'status' => SlotStatus::Available,
            'notes' => 'Auto-generated slot',
        ]);

        $this->dispatch('slot-created');
        $this->product->refresh();
    }

    public function deleteSlot(Slot $slot): void
    {
        if ($slot->status !== SlotStatus::Available) {
            $this->dispatch('error', 'Cannot delete slot that is already booked or in progress');
            return;
        }

        $slot->delete();
        $this->dispatch('slot-deleted');
        $this->product->refresh();
    }

    #[Computed]
    public function availableSlots()
    {
        return $this->product->slots()
            ->where('status', SlotStatus::Available)
            ->orderBy('slot_date')
            ->get();
    }

    #[Computed]
    public function bookedSlots()
    {
        return $this->product->slots()
            ->where('status', '!=', SlotStatus::Available)
            ->orderBy('slot_date')
            ->get();
    }
}; ?>

<div >
        <div class="mb-8 flex items-start justify-between">
            <div>
                <div class="mb-2 flex items-center gap-3">
                    <flux:heading size="xl">{{ $product->name }}</flux:heading>
                    <flux:badge variant="{{ $product->is_active ? 'lime' : 'zinc' }}">
                        {{ $product->is_active ? 'Active' : 'Inactive' }}
                    </flux:badge>
                </div>
                <flux:subheading>{{ $product->description }}</flux:subheading>
            </div>
            
            <div class="flex gap-2">
                <flux:button wire:click="createSlot" variant="primary" icon="plus">
                    Add Slot
                </flux:button>
                <flux:button :href="route('products.index')" variant="ghost">
                    Back to Products
                </flux:button>
            </div>
        </div>

        <div class="grid gap-8 lg:grid-cols-3">
            <div class="lg:col-span-2 space-y-8">
                <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
                    <flux:heading size="lg" class="mb-4">Product Details</flux:heading>
                    
                    <div class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <flux:text class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Product Type</flux:text>
                            <flux:text class="mt-1 capitalize">{{ str_replace('_', ' ', $product->type) }}</flux:text>
                        </div>
                        
                        <div>
                            <flux:text class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Base Price</flux:text>
                            <flux:heading size="lg" class="mt-1">${{ number_format($product->base_price, 2) }}</flux:heading>
                        </div>
                        
                        <div>
                            <flux:text class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Duration</flux:text>
                            <flux:text class="mt-1">{{ $product->duration_minutes }} minutes</flux:text>
                        </div>
                        
                        <div>
                            <flux:text class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Status</flux:text>
                            <flux:text class="mt-1">{{ $product->is_active ? 'Active' : 'Inactive' }}</flux:text>
                        </div>
                    </div>
                </div>

                @if($product->requirements->count() > 0)
                    <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
                        <flux:heading size="lg" class="mb-4">Requirements</flux:heading>
                        
                        <div class="space-y-4">
                            @foreach($product->requirements as $requirement)
                                <div class="flex items-start justify-between rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-600 dark:bg-zinc-700/50">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <flux:text class="font-medium">{{ $requirement->name }}</flux:text>
                                            @if($requirement->is_required)
                                                <flux:badge variant="red" size="sm">Required</flux:badge>
                                            @else
                                                <flux:badge variant="zinc" size="sm">Optional</flux:badge>
                                            @endif
                                        </div>
                                        
                                        @if($requirement->description)
                                            <flux:text size="sm" class="mt-1 text-zinc-600 dark:text-zinc-400">
                                                {{ $requirement->description }}
                                            </flux:text>
                                        @endif
                                        
                                        <flux:text size="sm" class="mt-2 capitalize text-zinc-500">
                                            Type: {{ str_replace('_', ' ', $requirement->type) }}
                                        </flux:text>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
                    <flux:heading size="lg" class="mb-4">Available Slots</flux:heading>
                    
                    @if($this->availableSlots->count() > 0)
                        <div class="grid gap-4 sm:grid-cols-2">
                            @foreach($this->availableSlots as $slot)
                                <div class="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-900/20">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <flux:text class="font-medium">{{ $slot->slot_date->format('M j, Y') }}</flux:text>
                                            <flux:text size="sm" class="text-green-700 dark:text-green-400">
                                                ${{ number_format($slot->price, 2) }}
                                            </flux:text>
                                        </div>
                                        
                                        <flux:button wire:click="deleteSlot({{ $slot->id }})" variant="danger" size="sm">
                                            <flux:icon.trash variant="micro" />
                                        </flux:button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <flux:text class="text-zinc-500">No available slots. Add some to start accepting bookings.</flux:text>
                    @endif
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
                    <flux:heading size="lg" class="mb-4">Statistics</flux:heading>
                    
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <flux:text>Total Slots</flux:text>
                            <flux:text class="font-semibold">{{ $product->slots->count() }}</flux:text>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <flux:text>Available</flux:text>
                            <flux:text class="font-semibold text-green-600">{{ $this->availableSlots->count() }}</flux:text>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <flux:text>Booked</flux:text>
                            <flux:text class="font-semibold text-blue-600">{{ $this->bookedSlots->count() }}</flux:text>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <flux:text>Requirements</flux:text>
                            <flux:text class="font-semibold">{{ $product->requirements->count() }}</flux:text>
                        </div>
                    </div>
                </div>

                @if($this->bookedSlots->count() > 0)
                    <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
                        <flux:heading size="lg" class="mb-4">Recent Bookings</flux:heading>
                        
                        <div class="space-y-3">
                            @foreach($this->bookedSlots->take(5) as $slot)
                                <div class="flex items-center justify-between">
                                    <div>
                                        <flux:text size="sm" class="font-medium">{{ $slot->slot_date->format('M j') }}</flux:text>
                                        <flux:badge variant="{{ $slot->status->variant() }}" size="sm">
                                            {{ $slot->status->label() }}
                                        </flux:badge>
                                    </div>
                                    <flux:text size="sm" class="text-zinc-500">
                                        ${{ number_format($slot->price, 2) }}
                                    </flux:text>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
</div>