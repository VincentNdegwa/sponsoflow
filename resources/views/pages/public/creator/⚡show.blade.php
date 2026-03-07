<?php

namespace App\Livewire; 

use App\Models\User;
use App\Models\Product;
use App\Models\Slot;
use App\Enums\SlotStatus;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Livewire\WithFileUploads; 

new #[Layout('layouts::guest'), Title('Creator Profile')] class extends Component {

        use WithFileUploads;
    public User $user;
    public $userProducts;
    public $availableSlots = [];
    public ?int $selectedProductId = null;
    public array $selectedSlots = [];
    public bool $showBookingDrawer = false;
    
    public array $guestData = [
        'name' => '',
        'email' => '',
        'company' => '',
    ];
    public array $requirementData = [];

    public function mount(User $user): void
    {
        if (!$user->is_public_profile) {
            abort(404);
        }
        $this->user = $user;
        $this->userProducts = $user->publicProducts()->get();
    }

    public function selectProduct($productId): void
    {
        $productId = (int) $productId; 
        $this->selectedProductId = $productId;
        $this->selectedSlots = [];
        $this->loadSlotsForProduct($productId);
    }

    public function clearProductFilter(): void
    {
        $this->selectedProductId = null;
        $this->selectedSlots = [];
        $this->availableSlots = [];
        $this->showBookingDrawer = false;
    }

    private function loadSlotsForProduct($productId): void
    {
        $productId = (int) $productId; // Ensure integer type
        
        $slots = $this->user->publicSlots()
            ->with('product')
            ->where('product_id', $productId)
            ->orderBy('slot_date')
            ->get();
            
        $groupedSlots = $slots->groupBy(fn($slot) => $slot->slot_date->format('Y-m'));
        
        $this->availableSlots = [];
        foreach ($groupedSlots as $monthYear => $monthSlots) {
            $this->availableSlots[$monthYear] = $monthSlots->map(function ($slot) {
                return [
                    'id' => $slot->id,
                    'price' => $slot->price,
                    'slot_date' => $slot->slot_date->toDateString(),
                    'slot_date_formatted' => [
                        'M_j' => $slot->slot_date->format('M j'),
                        'l' => $slot->slot_date->format('l'),
                    ]
                ];
            })->toArray();
        }
    }

    public function toggleSlotSelection($slotId): void
    {
        $slotId = (int) $slotId;
        
        if (in_array($slotId, $this->selectedSlots)) {
            $this->selectedSlots = array_diff($this->selectedSlots, [$slotId]);
        } else {
            $this->selectedSlots[] = $slotId;
        }
        
        if (count($this->selectedSlots) > 0 && !$this->showBookingDrawer) {
            $this->showBookingDrawer = true;
        } elseif (count($this->selectedSlots) === 0) {
            $this->showBookingDrawer = false;
        }
    }

    public function closeBookingDrawer(): void
    {
        $this->showBookingDrawer = false;
        $this->selectedSlots = [];
    }

    public function proceedToPayment(): void
    {
        $this->validate([
            'guestData.name' => 'required|string|max:255',
            'guestData.email' => 'required|email|max:255',
            'guestData.company' => 'nullable|string|max:255',
            'requirementData' => 'required|array',
        ]);

        $product = $this->selectedProduct;
        foreach ($product->requirements->where('is_required', true) as $requirement) {
            if (empty($this->requirementData[$requirement->id])) {
                $this->addError("requirementData.{$requirement->id}", 'This field is required');
                return;
            }
        }

        $this->dispatch('create-checkout-session', [
            'slots' => $this->selectedSlots,
            'guestData' => $this->guestData,
            'requirementData' => $this->requirementData,
        ]);
    }

    #[Computed]
    public function selectedProduct(): ?Product
    {
        return $this->selectedProductId ? $this->userProducts->firstWhere('id', $this->selectedProductId) : null;
    }

    #[Computed]
    public function selectedSlotModels()
    {
        return Slot::whereIn('id', $this->selectedSlots)->with('product')->get();
    }

    #[Computed]
    public function totalAmount(): float
    {
        return $this->selectedSlotModels->sum('price');
    }

}
?>

<div class="min-h-screen flex">
    <main @class([
        'flex-1 transition-all duration-300',
        'mr-[500px]' => $showBookingDrawer,
    ])>
        <div class="max-w-4xl mx-auto p-8 py-12">
            
            <div class="mb-12 text-center">
                <div class="w-24 h-24 mx-auto rounded-full bg-zinc-200 dark:bg-zinc-800 flex items-center justify-center overflow-hidden mb-4 ring-4 ring-accent/20">
                    @if ($user->profile_image)
                        <img src="{{ Storage::url($user->profile_image) }}" class="w-full h-full object-cover">
                    @else
                        <span class="text-xl font-bold text-zinc-500">{{ $user->initials() }}</span>
                    @endif
                </div>
                
                <flux:heading size="xl" class="mb-2">{{ $user->name }}</flux:heading>
                
                @if ($user->public_bio)
                    <flux:text class="text-zinc-600 dark:text-zinc-400 max-w-2xl mx-auto">
                        {{ $user->public_bio }}
                    </flux:text>
                @endif
            </div>

            <section class="mb-16">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <flux:heading size="xl" class="mb-2">Services Available</flux:heading>
                        <flux:text class="text-zinc-600 dark:text-zinc-400">Choose what you'd like to sponsor</flux:text>
                    </div>
                    @if ($selectedProductId)
                        <flux:button wire:click="clearProductFilter" variant="ghost" size="sm" icon="x-mark">
                            Clear Selection
                        </flux:button>
                    @endif
                </div>

                <div class="grid gap-6">
                    @foreach($userProducts as $product)
                        <div 
                            wire:click="selectProduct({{ $product->id }})"
                            @class([
                                'group cursor-pointer p-6 rounded-xl border-2 transition-all duration-300 relative',
                                'border-accent bg-accent/5' => $selectedProductId === $product->id,
                                'border-zinc-200 dark:border-zinc-700 hover:border-accent/50' => $selectedProductId !== $product->id,
                            ])
                        >
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-3">
                                        <flux:heading size="md">{{ $product->name }}</flux:heading>
                                        @if ($selectedProductId === $product->id)
                                            <flux:badge variant="solid" color="amber" size="sm">Selected</flux:badge>
                                        @endif
                                    </div>
                                    
                                    <flux:text class="text-zinc-600 dark:text-zinc-400 mb-4 leading-relaxed">
                                        {{ $product->description }}
                                    </flux:text>
                                    
                                    <div class="flex items-center gap-4 text-sm text-zinc-500">
                                        <span>{{ $product->sold_count }} completed</span>
                                        <span>{{ $product->requirements->count() }} requirements</span>
                                    </div>
                                </div>
                                
                                <div class="text-right ml-6">
                                    <flux:text size="xs" class="text-zinc-500 uppercase tracking-wide block mb-1">Starting at</flux:text>
                                    <flux:heading  class="text-accent">${{ number_format($product->base_price, 0) }}</flux:heading>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section @class([
                'transition-all duration-500',
                'opacity-30 blur-sm pointer-events-none' => !$selectedProductId
            ])>
                <div class="mb-8">
                    <flux:heading size="xl" class="mb-2">Available Dates</flux:heading>
                    <flux:text class="text-zinc-600 dark:text-zinc-400">
                        @if($selectedProductId)
                            Select your preferred time slots
                        @else
                            Choose a service to view available dates
                        @endif
                    </flux:text>
                </div>

                @if($selectedProductId && count($availableSlots) > 0)
                    <div class="space-y-12">
                        @foreach($availableSlots as $monthYear => $monthSlots)
                            <div>
                                <flux:heading size="md" class="mb-6 text-accent">
                                    {{ \Carbon\Carbon::createFromFormat('Y-m', $monthYear)->format('F Y') }}
                                </flux:heading>
                                
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                                    @foreach ($monthSlots as $slot)
                                        <button 
                                            wire:click="toggleSlotSelection({{ $slot['id'] }})"
                                            @class([
                                                'p-4 rounded-lg border-2 text-left transition-all duration-200 hover:shadow-md group',
                                                'border-accent bg-accent/10 shadow-lg' => in_array($slot['id'], $selectedSlots),
                                                'border-zinc-200 dark:border-zinc-700 hover:border-accent/50' => !in_array($slot['id'], $selectedSlots),
                                            ])
                                        >
                                            <div class="flex items-center justify-between mb-2">
                                                <flux:text  class="font-semibold">
                                                    {{ $slot['slot_date_formatted']['M_j'] }}
                                                </flux:text>
                                                @if(in_array($slot['id'], $selectedSlots))
                                                    <div class="w-2 h-2 rounded-full bg-accent"></div>
                                                @endif
                                            </div>
                                            
                                            <flux:text size="sm" class="text-zinc-500 mb-2">
                                                {{ $slot['slot_date_formatted']['l'] }}
                                            </flux:text>
                                            
                                            <flux:text size="sm" class="font-bold text-accent">
                                                ${{ number_format($slot['price'], 0) }}
                                            </flux:text>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-16 text-center border-2 border-dashed border-zinc-300 dark:border-zinc-600 rounded-xl">
                        <flux:icon.calendar-days class="w-12 h-12 mx-auto text-zinc-400 mb-4" />
                        <flux:text class="text-zinc-500">
                            @if(!$selectedProductId)
                                Select a service above to view available dates
                            @else
                                No availability for this service
                            @endif
                        </flux:text>
                    </div>
                @endif
            </section>
        </div>
    </main>

    <aside @class([
        'w-[500px] bg-zinc-50 dark:bg-zinc-900 border-l border-zinc-200 dark:border-zinc-800 fixed right-0 top-0 bottom-0 transform transition-transform duration-300 z-10 overflow-y-auto',
        'translate-x-0' => $showBookingDrawer,
        'translate-x-full' => !$showBookingDrawer,
    ])>
        <div class="p-8 h-full flex flex-col">
            <div class="flex items-center justify-between mb-8">
                <flux:heading >Booking Details</flux:heading>
                <flux:button wire:click="closeBookingDrawer" variant="ghost" size="sm" icon="x-mark" />
            </div>

            @if (count($selectedSlots) > 0)
                <div class="mb-8">
                    <flux:heading size="md" class="mb-4 text-accent">Selected Slots</flux:heading>
                    
                    <div class="space-y-3 mb-6">
                        @foreach ($this->selectedSlotModels as $slot)
                            <div class="flex justify-between items-center p-3 border border-zinc-200 dark:border-zinc-700 rounded-lg">
                                <div>
                                    <flux:text class="font-semibold">{{ $slot->slot_date->format('M j, Y') }}</flux:text>
                                    <flux:text size="sm" class="text-zinc-500">{{ $slot->slot_date->format('l') }}</flux:text>
                                </div>
                                <flux:text class="font-bold text-accent">${{ number_format($slot->price, 0) }}</flux:text>
                            </div>
                        @endforeach
                    </div>

                    <div class="border-t border-zinc-200 dark:border-zinc-700 pt-4 mb-8">
                        <div class="flex justify-between items-center">
                            <flux:text class="font-semibold">Total Amount</flux:text>
                            <flux:heading  class="text-accent">${{ number_format($this->totalAmount, 0) }}</flux:heading>
                        </div>
                    </div>
                </div>

                <div class="space-y-8 flex-1">
                    <div class="space-y-6">
                        <flux:heading size="md" class="text-accent">Contact Information</flux:heading>
                        
                        <div class="space-y-4">
                            <flux:input wire:model="guestData.name" label="Full Name" placeholder="Your full name" required />
                            <flux:input wire:model="guestData.email" label="Email Address" type="email" placeholder="your@email.com" required />
                            <flux:input wire:model="guestData.company" label="Company Name" placeholder="Your company or brand" />
                        </div>
                    </div>

                    @if ($this->selectedProduct && $this->selectedProduct->requirements->count() > 0)
                        <div class="space-y-6">
                            <flux:heading size="md" class="text-accent">Project Requirements</flux:heading>
                            
                            <div class="space-y-4">
                                @foreach ($this->selectedProduct->requirements as $requirement)
                                    <div>
                                        <flux:label>
                                            {{ $requirement->name }}
                                            @if ($requirement->is_required) 
                                                <span class="text-red-500">*</span> 
                                            @endif
                                        </flux:label>
                                        
                                        @if($requirement->type === 'textarea')
                                            <flux:textarea 
                                                wire:model="requirementData.{{ $requirement->id }}" 
                                                placeholder="{{ $requirement->description }}"
                                                rows="3" 
                                            />
                                        @else
                                            <flux:input 
                                                wire:model="requirementData.{{ $requirement->id }}" 
                                                :type="$requirement->type"
                                                placeholder="{{ $requirement->description }}"
                                            />
                                        @endif
                                        
                                        <flux:error name="requirementData.{{ $requirement->id }}" />
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div class="mt-auto pt-6 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:button wire:click="proceedToPayment" variant="primary" class="w-full"  icon-trailing="arrow-right">
                        Secure Payment
                    </flux:button>
                </div>
            @endif
        </div>
    </aside>
</div>