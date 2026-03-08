<?php

namespace App\Livewire;

use App\Models\User;
use App\Models\Product;
use App\Models\Slot;
use App\Models\Booking;
use App\Enums\SlotStatus;
use App\Enums\BookingType;
use App\Enums\BookingStatus;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Livewire\WithFileUploads;

new #[Layout('layouts::guest'), Title('Creator Profile')] class extends Component {
    use WithFileUploads;

    public User $user;
    public $userProducts;
    public $availableSlots = [];
    public ?int $selectedProductId = null;
    public array $selectedSlots = [];
    public bool $showBookingDrawer = false;
    public string $checkoutType = BookingType::INSTANT; // 'instant' or 'inquiry'
    public bool $isProcessing = false;
    public ?string $errorMessage = null;

    // Unified form data for both instant booking and inquiries
    public array $guestData = [
        'name' => '',
        'email' => '',
        'company' => '',
        'website' => '',
        'budget' => '',
        'pitch' => '',
        'campaign_goals' => '',
        'timeline_flexible' => true,
        'timeline_start' => '',
        'timeline_end' => '',
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

        if (!$this->hasSlots) {
            $this->checkoutType = 'inquiry';
            $this->showBookingDrawer = true;
        } else {
            $this->checkoutType = 'instant';
            $this->showBookingDrawer = false;
        }
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
        $productId = (int) $productId;

        $slots = $this->user->publicSlots()->where('product_id', $productId)->where('slot_date', '>=', now())->orderBy('slot_date')->get();

        $this->availableSlots = $slots->groupBy(fn($slot) => $slot->slot_date->format('Y-m'))->all();
    }

    public function openDrawer($type): void
    {
        $this->checkoutType = $type;
        $this->showBookingDrawer = true;
        $this->errorMessage = null;
    }

    public function toggleSlotSelection($slotId): void
    {
        $slotId = (int) $slotId;

        if (in_array($slotId, $this->selectedSlots)) {
            $this->selectedSlots = array_diff($this->selectedSlots, [$slotId]);
        } else {
            $this->selectedSlots[] = $slotId;
        }
    }

    public function closeBookingDrawer(): void
    {
        $this->showBookingDrawer = false;
        $this->selectedSlots = [];
        $this->checkoutType = 'instant';
        $this->errorMessage = null;
        $this->isProcessing = false;
    }

    public function processSubmission(): void
    {
        $this->checkoutType === 'instant' ? $this->proceedToPayment() : $this->createInquiry();
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

        $this->isProcessing = true;
        $this->errorMessage = null;

        try {
            $response = Http::post(route('creator.checkout', $this->user->public_slug), [
                'slot_ids' => $this->selectedSlots,
                'guest_data' => $this->guestData,
                'requirement_data' => $this->requirementData,
                'booking_type' => 'instant',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['checkout_url'])) {
                    $this->redirect($data['checkout_url'], navigate: false);
                } else {
                    $this->errorMessage = 'Payment setup failed. Please try again.';
                }
            } else {
                $responseData = $response->json();
                $this->errorMessage = $responseData['error'] ?? 'Payment processing failed. Please try again.';
            }
        } catch (\Exception $e) {
            $this->errorMessage = 'An unexpected error occurred. Please try again.';
        } finally {
            $this->isProcessing = false;
        }
    }

    private function createInquiry(): void
    {
        $this->validate([
            'guestData.name' => 'required|string|max:255',
            'guestData.email' => 'required|email|max:255',
            'guestData.budget' => 'required|numeric|min:1',
            'guestData.pitch' => 'required|string|min:20',
            'guestData.campaign_goals' => 'required|string|min:10',
        ]);

        $this->isProcessing = true;
        $this->errorMessage = null;

        try {
            $response = Http::post(route('creator.checkout', $this->user->public_slug), [
                'slot_ids' => [], // No specific slots for inquiries
                'product_id' => $this->selectedProductId, // Include product ID for inquiries
                'guest_data' => $this->guestData,
                'requirement_data' => [
                    'pitch' => $this->guestData['pitch'],
                    'campaign_goals' => $this->guestData['campaign_goals'],
                    'website' => $this->guestData['website'],
                    'timeline_flexible' => $this->guestData['timeline_flexible'],
                    'timeline_start' => $this->guestData['timeline_start'],
                    'timeline_end' => $this->guestData['timeline_end'],
                    'budget' => $this->guestData['budget'],
                ],
                'booking_type' => 'inquiry',
            ]);

            if ($response->successful()) {
                $this->showBookingDrawer = false;
                $this->reset(['guestData', 'selectedSlots', 'selectedProductId']);
                session()->flash('success', 'Your collaboration proposal has been sent successfully! The creator typically responds within 24 hours.');
            } else {
                $responseData = $response->json();
                $this->errorMessage = $responseData['error'] ?? 'Failed to submit inquiry. Please try again.';
            }
        } catch (\Exception $e) {
            $this->errorMessage = 'An unexpected error occurred. Please try again.';
        } finally {
            $this->isProcessing = false;
        }
    }

    #[Computed]
    public function selectedProduct(): ?Product
    {
        return $this->selectedProductId ? $this->userProducts->firstWhere('id', $this->selectedProductId) : null;
    }

    #[Computed]
    public function hasSlots(): bool
    {
        return !empty($this->availableSlots);
    }

    #[Computed]
    public function selectedSlotModels()
    {
        return Slot::whereIn('id', $this->selectedSlots)->get();
    }

    #[Computed]
    public function totalAmount(): float
    {
        return Slot::whereIn('id', $this->selectedSlots)->sum('price');
    }
};
?>

<div class="min-h-screen flex">
    {{-- Success Message --}}
    @if (session('success'))
        <div class="fixed top-4 right-4 z-50 max-w-md">
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg shadow-lg">
                <div class="flex items-center">
                    <flux:icon.check-circle class="w-5 h-5 mr-2 text-green-500" />
                    {{ session('success') }}
                </div>
            </div>
        </div>
    @endif

    <main @class([
        'flex-1 transition-all duration-300',
        'mr-[500px]' => $showBookingDrawer,
    ])>
        <div class="max-w-4xl mx-auto p-8 py-12">

            <div class="mb-12 text-center">
                <div
                    class="w-24 h-24 mx-auto rounded-full bg-zinc-200 dark:bg-zinc-800 flex items-center justify-center overflow-hidden mb-4 ring-4 ring-accent/20">
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
                        <flux:text class="text-zinc-600 dark:text-zinc-400">Choose what you'd like to sponsor
                        </flux:text>
                    </div>
                    @if ($selectedProductId)
                        <flux:button wire:click="clearProductFilter" variant="ghost" size="sm" icon="x-mark">
                            Clear Selection
                        </flux:button>
                    @endif
                </div>

                <div class="grid gap-6">
                    @foreach ($userProducts as $product)
                        <div wire:click="selectProduct({{ $product->id }})" @class([
                            'group cursor-pointer p-6 rounded-xl border-2 transition-all duration-300 relative',
                            'border-accent bg-accent/5' => $selectedProductId === $product->id,
                            'border-zinc-200 dark:border-zinc-700 hover:border-accent/50' =>
                                $selectedProductId !== $product->id,
                        ])>
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-3">
                                        <flux:heading size="md">{{ $product->name }}</flux:heading>
                                        @if ($selectedProductId === $product->id)
                                            <flux:badge variant="solid" color="amber" size="sm">Selected
                                            </flux:badge>
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
                                    <flux:text size="xs" class="text-zinc-500 uppercase tracking-wide block mb-1">
                                        Starting at</flux:text>
                                    <flux:heading class="text-accent">${{ number_format($product->base_price, 0) }}
                                    </flux:heading>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section @class([
                'transition-all duration-500',
                'opacity-30 blur-sm pointer-events-none' => !$selectedProductId,
            ])>
                <div class="mb-8">
                    <flux:heading size="xl" class="mb-2">Available Dates</flux:heading>
                    <flux:text class="text-zinc-600 dark:text-zinc-400">
                        @if ($selectedProductId)
                            @if ($this->hasSlots)
                                Select your preferred time slots
                            @else
                                This creator reviews proposals individually for the perfect brand fit
                            @endif
                        @else
                            Choose a service above to view booking options
                        @endif
                    </flux:text>
                </div>

                @if ($selectedProductId)
                    @if ($this->hasSlots)
                        {{-- Show available slots --}}
                        <div class="space-y-8 mb-8">
                            @foreach ($availableSlots as $monthYear => $monthSlots)
                                <div>
                                    <flux:heading size="md" class="mb-6 text-accent">
                                        {{ \Carbon\Carbon::createFromFormat('Y-m', $monthYear)->format('F Y') }}
                                    </flux:heading>

                                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                                        @foreach ($monthSlots as $slot)
                                            <button wire:click="toggleSlotSelection({{ $slot->id }})"
                                                @class([
                                                    'p-4 rounded-lg border-2 text-left transition-all duration-200 hover:shadow-md group',
                                                    'border-accent bg-accent/10 shadow-lg' => in_array(
                                                        $slot->id,
                                                        $selectedSlots),
                                                    'border-zinc-200 dark:border-zinc-700 hover:border-accent/50' => !in_array(
                                                        $slot->id,
                                                        $selectedSlots),
                                                ])>
                                                <div class="flex items-center justify-between mb-2">
                                                    <flux:text class="font-semibold">
                                                        {{ $slot->slot_date->format('M j') }}
                                                    </flux:text>
                                                    @if (in_array($slot->id, $selectedSlots))
                                                        <div class="w-2 h-2 rounded-full bg-accent"></div>
                                                    @endif
                                                </div>

                                                <flux:text size="sm" class="text-zinc-500 mb-2">
                                                    {{ $slot->slot_date->format('l') }}
                                                </flux:text>

                                                <flux:text size="sm" class="font-bold text-accent">
                                                    ${{ number_format($slot->price, 0) }}
                                                </flux:text>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div
                            class="p-6 bg-zinc-50 dark:bg-zinc-900 border-2 border-dashed border-zinc-200 dark:border-zinc-700 rounded-xl text-center">
                            <flux:text size="sm" class="mb-4 text-zinc-600 dark:text-zinc-400">
                                Need a custom date or special package?
                            </flux:text>
                            <flux:button wire:click="openDrawer('inquiry')" variant="filled" size="sm">
                                Request Custom Collaboration
                            </flux:button>
                        </div>
                    @else
                        {{-- No slots available - show inquiry only --}}
                        <div
                            class="text-center py-16 border-2 border-dashed border-zinc-300 dark:border-zinc-600 rounded-xl">
                            <flux:icon.chat-bubble-left-right class="w-12 h-12 mx-auto text-zinc-400 mb-4" />
                            <flux:heading size="md" class="mb-2">Custom Proposals Only</flux:heading>
                            <flux:text class="text-zinc-500 max-w-md mx-auto mb-6">
                                This creator hand-picks collaborations to ensure perfect brand alignment. The
                                collaboration form is now open.
                            </flux:text>
                        </div>
                    @endif
                @else
                    <div
                        class="py-16 text-center border-2 border-dashed border-zinc-300 dark:border-zinc-600 rounded-xl">
                        <flux:icon.cursor-arrow-rays class="w-12 h-12 mx-auto text-zinc-400 mb-4" />
                        <flux:text class="text-zinc-500">
                            Select a service above to view booking options
                        </flux:text>
                    </div>
                @endif
            </section>
        </div>
    </main>

    {{-- Bottom Action Bar for Selected Slots --}}
    @if ($selectedProductId && $this->hasSlots && count($selectedSlots) > 0)
        <div class="fixed bottom-6 left-1/2 -translate-x-1/2 w-full max-w-md px-4 z-40">
            <div class="bg-accent text-white p-4 rounded-xl shadow-2xl flex items-center justify-between">
                <div>
                    <span class="block text-xs font-semibold opacity-90">{{ count($selectedSlots) }} date(s)
                        selected</span>
                    <span class="text-lg font-bold">${{ number_format($this->totalAmount, 0) }}</span>
                </div>
                <flux:button wire:click="openDrawer('instant')" >
                    Continue
                </flux:button>
            </div>
        </div>
    @endif

    <aside @class([
        'w-[500px] bg-zinc-50 dark:bg-zinc-900 border-l border-zinc-200 dark:border-zinc-800 fixed right-0 top-0 bottom-0 transform transition-transform duration-300 z-10 overflow-y-auto',
        'translate-x-0' => $showBookingDrawer,
        'translate-x-full' => !$showBookingDrawer,
    ])>
        <div class="p-8 h-full flex flex-col">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <flux:heading>
                        {{ $checkoutType === 'instant' ? 'Complete Booking' : 'New Collaboration Pitch' }}
                    </flux:heading>
                    <flux:text size="sm" class="text-zinc-500 mt-1">
                        {{ $checkoutType === 'instant' ? 'Fill out your details to complete the booking' : 'Share your campaign vision to propose a collaboration' }}
                    </flux:text>
                </div>
                <flux:button wire:click="closeBookingDrawer" variant="ghost" size="sm" icon="x-mark" />
            </div>

            @if ($checkoutType === 'instant' && count($selectedSlots) > 0)
                <div class="mb-8">
                    <flux:heading size="md" class="mb-4 text-accent">Selected Slots</flux:heading>

                    <div class="space-y-3 mb-6">
                        @foreach ($this->selectedSlotModels as $slot)
                            <div
                                class="flex justify-between items-center p-3 border border-zinc-200 dark:border-zinc-700 rounded-lg">
                                <div>
                                    <flux:text class="font-semibold">{{ $slot->slot_date->format('M j, Y') }}
                                    </flux:text>
                                    <flux:text size="sm" class="text-zinc-500">{{ $slot->slot_date->format('l') }}
                                    </flux:text>
                                </div>
                                <flux:text class="font-bold text-accent">${{ number_format($slot->price, 0) }}
                                </flux:text>
                            </div>
                        @endforeach
                    </div>

                    <div class="border-t border-zinc-200 dark:border-zinc-700 pt-4 mb-8">
                        <div class="flex justify-between items-center">
                            <flux:text class="font-semibold">Total Amount</flux:text>
                            <flux:heading class="text-accent">${{ number_format($this->totalAmount, 0) }}
                            </flux:heading>
                        </div>
                    </div>
                </div>
            @endif

            <div class="space-y-8 flex-1">
                <div class="space-y-6">
                    <flux:heading size="md" class="text-accent">
                        {{ $checkoutType === 'instant' ? 'Contact Information' : 'Brand Information' }}
                    </flux:heading>

                    <div class="space-y-4">
                        <flux:input wire:model="guestData.name" label="Full Name" placeholder="Your full name"
                            required />
                        <flux:input wire:model="guestData.email" label="Email Address" type="email"
                            placeholder="your@email.com" required />

                        <div class="grid grid-cols-1 @if ($checkoutType === 'inquiry') md:grid-cols-2 @endif gap-4">
                            <flux:input wire:model="guestData.company" label="Company Name"
                                placeholder="Your company or brand" />
                            @if ($checkoutType === 'inquiry')
                                <flux:input wire:model="guestData.website" label="Website"
                                    placeholder="https://yourbrand.com" />
                            @endif
                        </div>
                    </div>
                </div>

                @if ($checkoutType === 'inquiry')
                    <div class="space-y-6">
                        <flux:heading size="md" class="text-accent">Campaign Details</flux:heading>

                        <div class="space-y-4">
                            <flux:input wire:model="guestData.budget" label="Proposed Budget" type="number"
                                placeholder="5000" prefix="$" required />

                            <flux:textarea wire:model="guestData.pitch" label="Campaign Pitch"
                                placeholder="Tell us about your brand and what you're looking to achieve with this collaboration..."
                                rows="4" required />

                            <flux:textarea wire:model="guestData.campaign_goals" label="Campaign Goals"
                                placeholder="What specific outcomes are you hoping for? (e.g., brand awareness, lead generation, etc.)"
                                rows="3" required />

                            <div class="space-y-3">
                                <flux:label>Timeline Preference</flux:label>
                                <div class="space-y-3">
                                    <flux:checkbox wire:model="guestData.timeline_flexible"
                                        label="Flexible timeline" />

                                    @if (!$guestData['timeline_flexible'])
                                        <div class="grid grid-cols-2 gap-4">
                                            <flux:input wire:model="guestData.timeline_start" label="Preferred Start"
                                                type="date" />
                                            <flux:input wire:model="guestData.timeline_end" label="Preferred End"
                                                type="date" />
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if ($checkoutType === 'instant' && $this->selectedProduct && $this->selectedProduct->requirements->count() > 0)
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

                                    @if ($requirement->type === 'textarea')
                                        <flux:textarea wire:model="requirementData.{{ $requirement->id }}"
                                            placeholder="{{ $requirement->description }}" rows="3" />
                                    @else
                                        <flux:input wire:model="requirementData.{{ $requirement->id }}"
                                            :type="$requirement->type"
                                            placeholder="{{ $requirement->description }}" />
                                    @endif

                                    <flux:error name="requirementData.{{ $requirement->id }}" />
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="mt-auto pt-6 border-t mb-4 border-zinc-200 dark:border-zinc-700">
                @if ($errorMessage)
                    <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
                        {{ $errorMessage }}
                    </div>
                @endif

                <flux:button wire:click="processSubmission" variant="primary" class="w-full"
                    icon-trailing="arrow-right" :disabled="$isProcessing">
                    @if ($isProcessing)
                        <flux:icon.arrow-path class="w-4 h-4 animate-spin mr-2" />
                        Processing...
                    @else
                        {{ $checkoutType === 'instant' ? 'Secure Payment' : 'Send Proposal' }}
                    @endif
                </flux:button>

                @if ($checkoutType === 'inquiry')
                    <flux:text size="xs" class="text-center text-zinc-500 mt-3">
                        The creator typically responds within 24 hours with a payment link or counter-proposal.
                    </flux:text>
                @endif
            </div>
        </div>
    </aside>
</div>
