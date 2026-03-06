<?php

use App\Models\User;
use App\Models\Product;
use App\Models\Slot;
use App\Enums\SlotStatus;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Carbon\Carbon;

new #[Layout('layouts::guest'), Title('Creator Profile')] class extends Component {
    public User $user;
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

        $this->user = $user->load(['publicProducts.requirements']);
    }

    public function selectProduct(int $productId): void
    {
        $this->selectedProductId = $productId;
        $this->selectedSlots = [];
    }

    public function clearProductFilter(): void
    {
        $this->selectedProductId = null;
        $this->selectedSlots = [];
    }

    public function toggleSlotSelection(int $slotId): void
    {
        if (in_array($slotId, $this->selectedSlots)) {
            $this->selectedSlots = array_diff($this->selectedSlots, [$slotId]);
        } else {
            $this->selectedSlots[] = $slotId;
        }
    }

    public function openBookingDrawer(): void
    {
        if (empty($this->selectedSlots)) {
            $this->dispatch('error', 'Please select at least one slot');
            return;
        }

        $this->showBookingDrawer = true;
    }

    public function closeBookingDrawer(): void
    {
        $this->showBookingDrawer = false;
    }

    public function proceedToPayment(): void
    {
        $validated = $this->validate([
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
        return $this->selectedProductId ? $this->user->publicProducts->firstWhere('id', $this->selectedProductId) : null;
    }

    #[Computed]
    public function selectedSlotModels()
    {
        if (empty($this->selectedSlots)) {
            return collect();
        }

        return Slot::whereIn('id', $this->selectedSlots)->with('product')->get();
    }

    #[Computed]
    public function totalAmount(): float
    {
        return $this->selectedSlotModels->sum('price');
    }

    #[Computed]
    public function availableSlots()
    {
        $query = $this->user->publicSlots()->with('product');

        if ($this->selectedProductId) {
            $query->where('product_id', $this->selectedProductId);
        }

        return $query
            ->orderBy('slot_date')
            ->get()
            ->groupBy(function ($slot) {
                return $slot->slot_date->format('Y-m');
            });
    }
}; ?>

<div class="bg-white dark:bg-zinc-900">
    <div class="bg-zinc-50 dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-700">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="text-center">
                <div style="width:90px; height:90px;" >
                    @if ($user->profile_image)
                        <img src="{{ Storage::url($user->profile_image) }}" alt="{{ $user->name }}"
                            class="w-full h-full rounded-full mx-auto object-cover border-4 border-white dark:border-zinc-700 shadow-sm">
                    @else
                        <div
                            class="rounded-full w-full h-full mx-auto bg-zinc-700 dark:bg-zinc-600 flex items-center justify-center text-white text-2xl font-semibold border-4 border-white dark:border-zinc-700 shadow-sm">
                            {{ $user->initials() }}
                        </div>
                    @endif
                </div>
                <flux:heading size="2xl" class="mb-4 text-zinc-900 dark:text-white">{{ $user->name }}
                </flux:heading>
                @if ($user->public_bio)
                    <flux:text class="text-zinc-600 dark:text-zinc-400 max-w-3xl mx-auto text-lg leading-relaxed">
                        {{ $user->public_bio }}</flux:text>
                @endif
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">

        <div class="grid gap-8 lg:grid-cols-3">
            <div class="lg:col-span-2 space-y-8">
                <div
                    class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                    <div class="p-6 border-b border-zinc-200 dark:border-zinc-700">
                        <flux:heading size="lg" class="mb-4">Services</flux:heading>

                        @if ($selectedProductId)
                            <div class="flex items-center gap-2 mb-4">
                                <flux:badge variant="blue" size="sm">
                                    Filtered: {{ $this->selectedProduct->name }}
                                </flux:badge>
                                <flux:button wire:click="clearProductFilter" variant="ghost" size="sm">
                                    Clear Filter
                                </flux:button>
                            </div>
                        @endif
                    </div>

                    <div class="grid gap-4 p-6 md:grid-cols-2">
                        @forelse($user->publicProducts as $product)
                            <div class="border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 cursor-pointer hover:border-zinc-300 dark:hover:border-zinc-600 transition-all duration-200 {{ $selectedProductId === $product->id ? 'bg-zinc-50 dark:bg-zinc-800 border-zinc-300 dark:border-zinc-600 shadow-sm' : 'bg-white dark:bg-zinc-900 hover:shadow-sm' }}"
                                wire:click="selectProduct({{ $product->id }})">
                                <div class="flex items-start justify-between mb-4">
                                    <flux:heading size="lg" class="text-zinc-900 dark:text-white">
                                        {{ $product->name }}</flux:heading>
                                    <div class="text-right">
                                        <flux:text size="2xl" class="font-bold text-zinc-900 dark:text-white">
                                            ${{ number_format($product->base_price, 0) }}
                                        </flux:text>
                                        <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">per slot
                                        </flux:text>
                                    </div>
                                </div>

                                <flux:text class="text-zinc-600 dark:text-zinc-400 mb-4 leading-relaxed">
                                    {{ $product->description }}
                                </flux:text>

                                @if ($product->requirements->count() > 0)
                                    <div class="mb-4">
                                        <flux:text size="sm"
                                            class="font-medium text-zinc-800 dark:text-zinc-200 mb-2">
                                            Requirements:
                                        </flux:text>
                                        <div class="space-y-1">
                                            @foreach ($product->requirements->take(3) as $req)
                                                <div class="flex items-start gap-2">
                                                    <flux:icon.check
                                                        class="w-3 h-3 text-zinc-500 dark:text-zinc-400 mt-1 shrink-0" />
                                                    <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">
                                                        {{ $req->name }}
                                                        @if ($req->is_required)
                                                            <span class="text-zinc-700 dark:text-zinc-300 font-medium">
                                                                (required)</span>
                                                        @endif
                                                    </flux:text>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <div class="flex items-center justify-between pt-2">
                                    @if ($product->sold_count > 0)
                                        <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                                            {{ $product->sold_count }} completed
                                            project{{ $product->sold_count > 1 ? 's' : '' }}
                                        </flux:text>
                                    @else
                                        <div></div>
                                    @endif
                                    @if ($selectedProductId === $product->id)
                                        <flux:text size="sm" class="font-medium text-zinc-700 dark:text-zinc-300">
                                            Selected
                                        </flux:text>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="col-span-2 text-center py-8">
                                <flux:text class="text-zinc-500">No services available</flux:text>
                            </div>
                        @endforelse
                    </div>
                </div>

                <div
                    class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                    <div class="p-6 border-b border-zinc-200 dark:border-zinc-700">
                        <flux:heading size="lg">Available Dates</flux:heading>
                    </div>

                    <div class="p-6 space-y-8">
                        @forelse($this->availableSlots as $monthYear => $monthSlots)
                            <div class="mb-8">
                                <flux:heading size="lg" class="mb-6 text-zinc-800 dark:text-zinc-200">
                                    {{ \Carbon\Carbon::createFromFormat('Y-m', $monthYear)->format('F Y') }}
                                </flux:heading>

                                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                    @foreach ($monthSlots as $slot)
                                        <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 cursor-pointer transition-all duration-200 {{ in_array($slot->id, $selectedSlots) ? 'bg-zinc-100 dark:bg-zinc-800 border-zinc-400 dark:border-zinc-600 shadow-sm' : 'bg-white dark:bg-zinc-900 hover:border-zinc-300 dark:hover:border-zinc-600 hover:shadow-sm' }}"
                                            wire:click="toggleSlotSelection({{ $slot->id }})">
                                            <div class="flex items-center justify-between mb-3">
                                                <flux:text class="font-semibold text-zinc-900 dark:text-white">
                                                    {{ $slot->slot_date->format('M j') }}
                                                </flux:text>
                                                @if (in_array($slot->id, $selectedSlots))
                                                    <flux:icon.check class="w-4 h-4 text-zinc-700 dark:text-zinc-300" />
                                                @endif
                                            </div>

                                            @if ($slot->slot_time)
                                                <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400 mb-3">
                                                    {{ $slot->slot_time->format('g:i A') }}
                                                </flux:text>
                                            @endif

                                            <div class="space-y-2">
                                                <div class="flex items-center justify-between">
                                                    <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">
                                                        {{ $slot->product->name }}
                                                    </flux:text>
                                                </div>
                                                <flux:text class="font-bold text-zinc-900 dark:text-white">
                                                    ${{ number_format($slot->price, 0) }}
                                                </flux:text>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-8">
                                <flux:text class="text-zinc-500">
                                    @if ($selectedProductId)
                                        No available dates for this service
                                    @else
                                        No available dates
                                    @endif
                                </flux:text>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                @if (count($selectedSlots) > 0)
                    <div
                        class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 sticky top-6 shadow-sm">
                        <flux:heading size="lg" class="mb-6 text-zinc-900 dark:text-white">Booking Summary
                        </flux:heading>

                        <div class="space-y-3 mb-6">
                            @foreach ($this->selectedSlotModels as $slot)
                                <div
                                    class="flex items-center justify-between p-4 bg-zinc-50 dark:bg-zinc-800 rounded-lg">
                                    <div>
                                        <flux:text class="font-medium text-zinc-900 dark:text-white">
                                            {{ $slot->slot_date->format('M j, Y') }}
                                        </flux:text>
                                        <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                                            {{ $slot->product->name }}
                                        </flux:text>
                                    </div>
                                    <flux:text class="font-semibold text-zinc-900 dark:text-white">
                                        ${{ number_format($slot->price, 0) }}
                                    </flux:text>
                                </div>
                            @endforeach
                        </div>

                        <div class="border-t border-zinc-200 dark:border-zinc-700 pt-6 mb-6">
                            <div class="flex items-center justify-between">
                                <flux:text class="text-lg font-medium text-zinc-900 dark:text-white">Total</flux:text>
                                <flux:text class="text-2xl font-bold text-zinc-900 dark:text-white">
                                    ${{ number_format($this->totalAmount, 0) }}
                                </flux:text>
                            </div>
                        </div>

                        <flux:button wire:click="openBookingDrawer" variant="primary" class="w-full" size="lg">
                            Continue to Checkout
                        </flux:button>
                    </div>
                @endif
            </div>
        </div>
    </div>


    <flux:modal wire:model.self="showBookingDrawer" class="max-w-2xl">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Complete Your Booking</flux:heading>
                <flux:text class="mt-2">Fill in your details and requirements</flux:text>
            </div>

            <div class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <flux:field>
                        <flux:label>Full Name</flux:label>
                        <flux:input wire:model="guestData.name" placeholder="Your full name" required />
                        <flux:error name="guestData.name" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Email</flux:label>
                        <flux:input wire:model="guestData.email" type="email" placeholder="your@email.com"
                            required />
                        <flux:error name="guestData.email" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>Company (Optional)</flux:label>
                    <flux:input wire:model="guestData.company" placeholder="Your company name" />
                    <flux:error name="guestData.company" />
                </flux:field>

                @if ($this->selectedProduct && $this->selectedProduct->requirements->count() > 0)
                    <div class="border-t pt-4">
                        <flux:text class="font-medium mb-4">Service Requirements</flux:text>

                        @foreach ($this->selectedProduct->requirements as $requirement)
                            <flux:field class="mb-4">
                                <flux:label>
                                    {{ $requirement->name }}
                                    @if ($requirement->is_required)
                                        <span class="text-red-500">*</span>
                                    @endif
                                </flux:label>

                                @if ($requirement->description)
                                    <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400 mb-2">
                                        {{ $requirement->description }}
                                    </flux:text>
                                @endif

                                @if ($requirement->type === 'file')
                                    <flux:input wire:model="requirementData.{{ $requirement->id }}" type="file" />
                                @elseif($requirement->type === 'url')
                                    <flux:input wire:model="requirementData.{{ $requirement->id }}" type="url"
                                        placeholder="https://" />
                                @elseif($requirement->type === 'textarea')
                                    <flux:textarea wire:model="requirementData.{{ $requirement->id }}"
                                        rows="3" />
                                @else
                                    <flux:input wire:model="requirementData.{{ $requirement->id }}" type="text" />
                                @endif

                                <flux:error name="requirementData.{{ $requirement->id }}" />
                            </flux:field>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="border-t pt-4">
                <div class="flex items-center justify-between mb-4">
                    <flux:text>Total Amount</flux:text>
                    <flux:heading size="lg" class="text-green-600">
                        ${{ number_format($this->totalAmount, 0) }}
                    </flux:heading>
                </div>
            </div>

            <div class="flex gap-3">
                <flux:spacer />
                <flux:button wire:click="closeBookingDrawer" variant="ghost">
                    Cancel
                </flux:button>
                <flux:button wire:click="proceedToPayment" variant="primary">
                    Proceed to Payment
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
