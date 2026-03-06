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
    public array $batchPreview = [];
    
    public array $slotForm = [
        'slot_date' => '',
        'slot_time' => null,
        'price' => '',
        'notes' => null,
    ];
    
    public array $batchForm = [
        'start_date' => '',
        'end_date' => '',
        'frequency' => 'weekly',
        'day_of_week' => 1,
        'day_of_month' => 1,
        'price_override' => null,
        'notes_template' => null,
    ];

    public function mount(Product $product): void
    {
        if ($product->workspace_id !== Auth::user()->currentWorkspace()->id) {
            abort(404);
        }
        $this->product = $product->load('requirements', 'slots');
        $this->slotForm['price'] = $this->product->base_price;
        $this->slotForm['slot_date'] = now()->addWeek()->format('Y-m-d');
        
        $this->batchForm['start_date'] = now()->addWeek()->format('Y-m-d');
        $this->batchForm['end_date'] = now()->addMonths(3)->format('Y-m-d');
    }

    public function openBatchModal(): void
    {
        $this->batchForm['start_date'] = now()->addWeek()->format('Y-m-d');
        $this->batchForm['end_date'] = now()->addMonths(3)->format('Y-m-d');
        $this->generateBatchPreview();
        $this->modal('batch-generate')->show();
    }

    public function updatedBatchForm(): void
    {
        $this->generateBatchPreview();
    }

    private function generateBatchPreview(): void
    {
        if (empty($this->batchForm['start_date']) || empty($this->batchForm['end_date'])) {
            $this->batchPreview = [];
            return;
        }

        try {
            $startDate = \Carbon\Carbon::parse($this->batchForm['start_date']);
            $endDate = \Carbon\Carbon::parse($this->batchForm['end_date']);
            $frequency = $this->batchForm['frequency'];
            
            $dates = [];
            $currentDate = $startDate->copy();
            
            while ($currentDate <= $endDate && count($dates) < 100) {
                $shouldInclude = false;
                
                switch ($frequency) {
                    case 'daily':
                        $shouldInclude = true;
                        break;
                    case 'weekly':
                        $shouldInclude = $currentDate->dayOfWeek === (int) $this->batchForm['day_of_week'];
                        break;
                    case 'monthly':
                        $shouldInclude = $currentDate->day === (int) $this->batchForm['day_of_month'];
                        break;
                }
                
                if ($shouldInclude) {
                    $existingSlot = $this->product->slots()
                        ->whereDate('slot_date', $currentDate->format('Y-m-d'))
                        ->exists();
                        
                    if (!$existingSlot) {
                        $dates[] = $currentDate->format('M j, Y');
                    }
                }
                
                $currentDate->addDay();
            }
            
            $this->batchPreview = $dates;
        } catch (\Exception $e) {
            $this->batchPreview = [];
        }
    }

    public function generateBatchSlots(): void
    {
        $validated = $this->validate([
            'batchForm.start_date' => 'required|date|after_or_equal:today',
            'batchForm.end_date' => 'required|date|after:batchForm.start_date',
            'batchForm.frequency' => 'required|in:daily,weekly,monthly',
            'batchForm.day_of_week' => 'required_if:batchForm.frequency,weekly|integer|between:0,6',
            'batchForm.day_of_month' => 'required_if:batchForm.frequency,monthly|integer|between:1,31',
            'batchForm.price_override' => 'nullable|numeric|min:0',
            'batchForm.notes_template' => 'nullable|string|max:500',
        ]);

        $startDate = \Carbon\Carbon::parse($validated['batchForm']['start_date']);
        $endDate = \Carbon\Carbon::parse($validated['batchForm']['end_date']);
        $frequency = $validated['batchForm']['frequency'];
        
        $slotsCreated = 0;
        $currentDate = $startDate->copy();
        
        while ($currentDate <= $endDate && $slotsCreated < 100) {
            $shouldInclude = false;
            
            switch ($frequency) {
                case 'daily':
                    $shouldInclude = true;
                    break;
                case 'weekly':
                    $shouldInclude = $currentDate->dayOfWeek === (int) $validated['batchForm']['day_of_week'];
                    break;
                case 'monthly':
                    $shouldInclude = $currentDate->day === (int) $validated['batchForm']['day_of_month'];
                    break;
            }
            
            if ($shouldInclude) {
                $existingSlot = $this->product->slots()
                    ->whereDate('slot_date', $currentDate->format('Y-m-d'))
                    ->exists();
                    
                if (!$existingSlot) {
                    $this->product->slots()->create([
                        'workspace_id' => Auth::user()->currentWorkspace()->id,
                        'slot_date' => $currentDate->format('Y-m-d'),
                        'price' => $validated['batchForm']['price_override'] ?: $this->product->base_price,
                        'status' => SlotStatus::Available,
                        'notes' => $validated['batchForm']['notes_template'] ?: "Batch generated slot",
                    ]);
                    $slotsCreated++;
                }
            }
            
            $currentDate->addDay();
        }

        $this->dispatch('success', "Successfully generated {$slotsCreated} slots!");
        $this->modal('batch-generate')->close();
        $this->reset('batchForm', 'batchPreview');
        $this->product->refresh();
    }

    public function openSlotModal(): void
    {
        $this->slotForm['price'] = $this->product->base_price;
        $this->slotForm['slot_date'] = now()->addWeek()->format('Y-m-d');
        $this->modal('create-slot')->show();
    }

    public function createSlot(): void
    {
        $validated = $this->validate([
            'slotForm.slot_date' => 'required|date|after:today',
            'slotForm.slot_time' => 'nullable|date_format:H:i',
            'slotForm.price' => 'required|numeric|min:0',
            'slotForm.notes' => 'nullable|string|max:500',
        ]);
        
        $this->product->slots()->create([
            'workspace_id' => Auth::user()->currentWorkspace()->id,
            'slot_date' => $validated['slotForm']['slot_date'],
            'slot_time' => $validated['slotForm']['slot_time'],
            'price' => $validated['slotForm']['price'],
            'status' => SlotStatus::Available,
            'notes' => $validated['slotForm']['notes'],
        ]);

        $this->dispatch('success', 'Slot created successfully!');
        $this->modal('create-slot')->close();
        $this->reset('slotForm');
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

<div>
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
                <flux:button wire:click="openBatchModal" variant="filled" icon="squares-plus" class="bg-blue-600 hover:bg-blue-700">
                    Batch Generate
                </flux:button>
                <flux:button wire:click="openSlotModal" variant="primary" icon="plus">
                    Add Single Slot
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
                    <flux:heading size="lg" class="mb-4">Inventory Health</flux:heading>
                    
                    @php
                        $totalSlots = $product->slots->count();
                        $availableCount = $this->availableSlots->count();
                        $bookedCount = $this->bookedSlots->count();
                        $fillRate = $totalSlots > 0 ? round(($bookedCount / $totalSlots) * 100) : 0;
                        $projectedRevenue = $this->bookedSlots->sum('price');
                        $availableRevenue = $this->availableSlots->sum('price');
                    @endphp
                    
                    <div class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
                        <div class="flex items-center justify-between">
                            <flux:text class="font-medium text-blue-800 dark:text-blue-200">Fill Rate</flux:text>
                            <flux:heading size="lg" class="text-blue-900 dark:text-blue-100">{{ $fillRate }}%</flux:heading>
                        </div>
                        <div class="mt-2 h-2 rounded-full bg-blue-200 dark:bg-blue-800">
                            <div class="h-2 rounded-full bg-blue-600" style="width: {{ $fillRate }}%"></div>
                        </div>
                    </div>
                    
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="text-center rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-900/20">
                            <flux:text class="font-semibold text-green-900 dark:text-green-100">Available</flux:text>
                            <flux:heading size="xl" class="text-green-800 dark:text-green-200">{{ $availableCount }}</flux:heading>
                            <flux:text size="sm" class="text-green-600 dark:text-green-400">${{ number_format($availableRevenue, 0) }} potential</flux:text>
                        </div>
                        
                        <div class="text-center rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
                            <flux:text class="font-semibold text-blue-900 dark:text-blue-100">Booked</flux:text>
                            <flux:heading size="xl" class="text-blue-800 dark:text-blue-200">{{ $bookedCount }}</flux:heading>
                            <flux:text size="sm" class="text-blue-600 dark:text-blue-400">${{ number_format($projectedRevenue, 0) }} confirmed</flux:text>
                        </div>
                    </div>
                    
                    <div class="mt-6 space-y-3 border-t pt-4">
                        <div class="flex items-center justify-between">
                            <flux:text>Total Inventory</flux:text>
                            <flux:text class="font-semibold">{{ $totalSlots }} slots</flux:text>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <flux:text>Requirements</flux:text>
                            <flux:text class="font-semibold">{{ $product->requirements->count() }} fields</flux:text>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <flux:text>Avg. Price</flux:text>
                            <flux:text class="font-semibold">${{ $totalSlots > 0 ? number_format($product->slots->avg('price'), 2) : '0.00' }}</flux:text>
                        </div>
                        
                        <div class="flex items-center justify-between border-t pt-3">
                            <flux:text class="font-medium">Total Revenue Potential</flux:text>
                            <flux:heading size="lg" class="text-green-600">${{ number_format($projectedRevenue + $availableRevenue, 0) }}</flux:heading>
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
    <x-products.modals.create-slot 
        :product="$product" 
    />
    
    <x-products.modals.create-bulk-slots 
        :product="$product"
        :batchForm="$batchForm"
        :batchPreview="$batchPreview"
    />
</div>
