<?php

namespace App\Livewire;

use App\Models\User;
use App\Models\Product;
use App\Models\Campaign;
use App\Models\Slot;
use App\Models\Booking;
use App\Models\Workspace;
use App\Support\InquiryCampaignSkeleton;
use App\Services\BookingService;
use App\Services\CampaignService;
use App\Enums\SlotStatus;
use App\Enums\BookingType;
use App\Enums\BookingStatus;
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
    public ?Workspace $workspace = null;
    public ?User $brandUser = null;
    public ?Workspace $brandWorkspace = null;
    public $availableSlots = [];
    public ?int $selectedProductId = null;
    public array $selectedSlots = [];
    public bool $showBookingDrawer = false;
    public string $checkoutType = 'instant';
    public bool $isProcessing = false;
    public ?string $errorMessage = null;
    public string $campaignSelectionMode = 'new';
    public $selectedCampaignId = null;

    public array $guestData = [
        'name' => '',
        'email' => '',
        'company' => '',
        'budget' => '',
        'campaign_name' => '',
        'main_goal' => '',
        'pitch' => '',
        'product_service_link' => '',
        'mandatory_mention' => '',
    ];
    public array $requirementData = [];

    private array $slotModelCache = [];
    
    private function fillGuestDataFromAuth(): void
    {
        if ($this->brandUser) {
            $this->guestData['name'] = $this->brandUser->name;
            $this->guestData['email'] = $this->brandUser->email;
            $this->guestData['company'] = $this->brandWorkspace?->name ?? '';
        }
    }
    
    #[Computed]
    public function isGuestUser(): bool
    {
        return !$this->brandUser;
    }
    
    #[Computed]
    public function canMakeBooking(): bool
    {
        return $this->bookingRestrictionMessage() === null;
    }

    #[Computed]
    public function bookingRestrictionMessage(): ?string
    {
        if (! $this->brandUser) {
            return null;
        }

        if ($this->brandUser->id === $this->user->id) {
            return 'You cannot book your own services. Switch to a brand account to continue.';
        }

        if (! $this->isBrandUser($this->brandUser)) {
            return 'You are signed in with a non-brand account. Switch to a brand account to book or send proposals.';
        }

        return null;
    }

    public function mount(User $user): void
    {
        if (! $user->is_public_profile) {
            abort(404);
        }

        $this->user = $user;
        $this->workspace = $user->currentWorkspace();
        $this->brandUser = auth()->user();
        $this->brandWorkspace = $this->brandUser?->currentWorkspace();

        $this->fillGuestDataFromAuth();
    }

    #[Computed]
    public function userProducts()
    {
        return $this->user->publicProducts()
            ->withCount([
                'slots as available_slots_count' => fn ($query) => $query
                    ->where('status', SlotStatus::Available)
                    ->whereDate('slot_date', '>=', now()),
            ])
            ->get();
    }

    public function selectProduct($productId): void
    {
        $productId = (int) $productId;
        $this->selectedProductId = $productId;
        $this->selectedSlots = [];
        $this->availableSlots = [];
        $this->slotModelCache = [];
        $this->checkoutType = 'instant';
        $this->showBookingDrawer = false;
        $this->errorMessage = null;
        $this->campaignSelectionMode = 'new';
        $this->selectedCampaignId = null;
    }

    public function viewSlots($productId): void
    {
        $productId = (int) $productId;
        $this->selectProduct($productId);
        $this->loadSlotsForProduct($productId);
    }

    public function openCustomBooking($productId): void
    {
        $productId = (int) $productId;
        $this->selectProduct($productId);

        if (! $this->canMakeBooking()) {
            return;
        }

        $this->openDrawer('inquiry');
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

        $slots = $this->user->publicSlots()
            ->where('product_id', $productId)
            ->whereDate('slot_date', '>=', now())
            ->orderBy('slot_date')
            ->get(['id', 'slot_date', 'price']);

        $this->availableSlots = $slots->groupBy(fn ($slot) => $slot->slot_date->format('Y-m'))->all();
        $this->slotModelCache = [];
    }

    public function openDrawer($type): void
    {
        if (! $this->canMakeBooking()) {
            return;
        }
        
        $this->checkoutType = $type;
        if ($type === 'inquiry') {
            $this->initializeInquirySkeleton();
        }
        $this->showBookingDrawer = true;
        $this->errorMessage = null;
    }

    private function initializeInquirySkeleton(): void
    {
        $product = $this->selectedProduct;

        if (! $product) {
            return;
        }

        if ($this->guestData['campaign_name'] === '') {
            $this->guestData['campaign_name'] = InquiryCampaignSkeleton::defaultCampaignName($this->user->name);
        }

        $budget = (float) $product->base_price;
        $this->guestData['budget'] = (string) $budget;

        if (! $this->isGuestUser() && $this->campaignSelectionMode === 'existing' && empty($this->brandCampaignOptions())) {
            $this->campaignSelectionMode = 'new';
            $this->selectedCampaignId = null;
        }
    }

    public function updatedCampaignSelectionMode(string $value): void
    {
        if (! $this->hasBrandCampaignOptions()) {
            $this->campaignSelectionMode = 'new';
            $this->selectedCampaignId = null;

            return;
        }

        if ($value !== 'existing') {
            $this->selectedCampaignId = null;
        }
    }

    #[Computed]
    public function hasBrandCampaignOptions(): bool
    {
        return $this->brandCampaignOptions()->isNotEmpty();
    }

    #[Computed]
    public function usingExistingCampaignForInquiry(): bool
    {
        return ! $this->isGuestUser()
            && $this->hasBrandCampaignOptions()
            && $this->campaignSelectionMode === 'existing';
    }

    #[Computed]
    public function selectedBrandCampaign(): ?Campaign
    {
        if (! $this->usingExistingCampaignForInquiry() || ! $this->selectedCampaignId) {
            return null;
        }

        return $this->brandCampaignOptions()->firstWhere('id', (int) $this->selectedCampaignId);
    }

    #[Computed]
    public function inquirySkeletonSchema(): array
    {
        return InquiryCampaignSkeleton::uiSchema();
    }

    #[Computed]
    public function brandCampaignOptions()
    {
        if ($this->isGuestUser() || ! $this->brandWorkspace) {
            return collect();
        }

        return app(CampaignService::class)->visibleForWorkspace($this->brandWorkspace);
    }

    public function toggleSlotSelection($slotId): void
    {
        if (! $this->canMakeBooking()) {
            return;
        }
        
        $slotId = (int) $slotId;
        if (in_array($slotId, $this->selectedSlots)) {
            $this->selectedSlots = array_values(array_diff($this->selectedSlots, [$slotId]));
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
        if ($this->checkoutType === 'instant') {
            $this->proceedToPayment();
        } else {
            $this->createInquiry();
        }
    }

    public function proceedToPayment(): void
    {
        if (! $this->canMakeBooking()) {
            $this->errorMessage = 'You are not authorized to make bookings.';
            return;
        }
        
        $validation = ['requirementData' => 'required|array'];
        
        if ($this->isGuestUser()) {
            $validation['guestData.name'] = 'required|string|max:255';
            $validation['guestData.email'] = 'required|email|max:255';
            $validation['guestData.company'] = 'nullable|string|max:255';
        }
        
        $this->validate($validation);

        $product = $this->selectedProduct;
        if ($product) {
            foreach ($product->requirements->where('is_required', true) as $requirement) {
                if (empty($this->requirementData[$requirement->id])) {
                    $this->addError("requirementData.{$requirement->id}", 'This field is required');
                    return;
                }
            }
        }

        $this->isProcessing = true;
        $this->errorMessage = null;

        try {
            $data = [
                'creator' => $this->user,
                'slot_ids' => $this->selectedSlots,
                'requirement_data' => $this->requirementData,
            ];
            
            if ($this->isGuestUser()) {
                $data['guest_data'] = $this->guestData;
            } else {
                $data['brand_user_id'] = $this->brandUser->id;
                $data['brand_workspace_id'] = $this->brandWorkspace?->id;
            }
            
            $result = app(BookingService::class)->createInstantBooking($data);

            if ($result['success']) {
                if (isset($result['checkout_url'])) {
                    $this->redirect($result['checkout_url'], navigate: false);
                } else {
                    $this->errorMessage = 'Payment setup failed. Please try again.';
                }
            } else {
                $this->errorMessage = $result['error'] ?? 'Payment processing failed. Please try again.';
            }
        } catch (\Exception $e) {
            $this->errorMessage = 'An unexpected error occurred. Please try again.';
        } finally {
            $this->isProcessing = false;
        }
    }

    private function createInquiry(): void
    {
        if (! $this->canMakeBooking()) {
            $this->errorMessage = 'You are not authorized to make inquiries.';
            return;
        }

        if (! $this->isGuestUser() && ! $this->hasBrandCampaignOptions()) {
            $this->campaignSelectionMode = 'new';
            $this->selectedCampaignId = null;
        }
        
        $requiresInquirySkeleton = $this->isGuestUser() || ! $this->usingExistingCampaignForInquiry();

        $validation = [];

        if ($requiresInquirySkeleton) {
            $validation['guestData.budget'] = 'required|numeric|min:1';
            $validation['guestData.campaign_name'] = 'required|string|max:255';
            $validation['guestData.main_goal'] = 'required|in:awareness,sales,content_creation';
            $validation['guestData.pitch'] = 'required|string';
            $validation['guestData.product_service_link'] = 'required|url|max:500';
            $validation['guestData.mandatory_mention'] = 'nullable|string|max:255';
        }
        
        if ($this->isGuestUser()) {
            $validation['guestData.name'] = 'required|string|max:255';
            $validation['guestData.email'] = 'required|email|max:255';
        } else {
            $validation['campaignSelectionMode'] = 'required|in:new,existing';
            if ($this->campaignSelectionMode === 'existing') {
                $validation['selectedCampaignId'] = 'required|integer|min:1';
            }
        }
        
        $this->validate($validation);

        $this->isProcessing = true;
        $this->errorMessage = null;

        try {
            $requirementData = [];

            if ($requiresInquirySkeleton) {
                $requirementData = [
                    'campaign_name' => $this->guestData['campaign_name'],
                    'main_goal' => $this->guestData['main_goal'],
                    'pitch' => $this->guestData['pitch'],
                    'product_service_link' => $this->guestData['product_service_link'],
                    'mandatory_mention' => $this->guestData['mandatory_mention'],
                    'budget' => $this->guestData['budget'],
                ];
            }

            $data = [
                'creator' => $this->user,
                'product_id' => $this->selectedProductId,
                'requirement_data' => $requirementData,
            ];
            
            if ($this->isGuestUser()) {
                $data['guest_data'] = $this->guestData;
            } else {
                $data['brand_user_id'] = $this->brandUser->id;
                $data['brand_workspace_id'] = $this->brandWorkspace?->id;
                $data['campaign_mode'] = $this->campaignSelectionMode;
                if ($this->campaignSelectionMode === 'existing') {
                    $data['campaign_id'] = $this->selectedCampaignId;
                }
            }
            
            $result = app(BookingService::class)->createInquiry($data);

            if ($result['success']) {
                $this->showBookingDrawer = false;
                $this->reset(['guestData', 'selectedSlots', 'selectedProductId', 'campaignSelectionMode', 'selectedCampaignId']);
                $this->fillGuestDataFromAuth();
                $this->campaignSelectionMode = 'new';
                session()->flash('success', 'Your collaboration proposal has been sent successfully! The creator typically responds within 24 hours.');
            } else {
                $this->errorMessage = $result['error'] ?? 'Failed to submit inquiry. Please try again.';
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
        if (! $this->selectedProductId) {
            return null;
        }

        return $this->userProducts->firstWhere('id', $this->selectedProductId);
    }

    #[Computed]
    public function hasSlots(): bool
    {
        return !empty($this->availableSlots);
    }

    #[Computed]
    public function selectedSlotModels()
    {
        $ids = $this->selectedSlots;
        if (empty($ids)) {
            return collect();
        }
        $missing = array_diff($ids, array_keys($this->slotModelCache));
        if ($missing) {
            $slots = \App\Models\Slot::whereIn('id', $missing)->get(['id', 'slot_date', 'price']);
            foreach ($slots as $slot) {
                $this->slotModelCache[$slot->id] = $slot;
            }
        }
        return collect($ids)->map(fn($id) => $this->slotModelCache[$id] ?? null)->filter();
    }

    #[Computed]
    public function totalAmount(): float
    {
        $slots = $this->selectedSlotModels();
        return $slots->sum('price');
    }
    
    private function isBrandUser(User $user): bool
    {
        $workspace = $user->currentWorkspace();
        if ($workspace && $workspace->isBrand()) {
            return true;
        }
        
        return $user->hasRole(['brand-admin', 'brand-contributor']);
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
                    @foreach ($this->userProducts as $product)
                        <div @class([
                            'group p-6 rounded-xl border-2 transition-all duration-300 relative',
                            'border-accent bg-accent/5' => $selectedProductId === $product->id,
                            'border-zinc-200 dark:border-zinc-700' =>
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

                                    <div class="flex flex-wrap gap-2">
                                        @if ($product->available_slots_count > 0)
                                            <flux:button wire:click="viewSlots({{ $product->id }})" variant="outline"
                                                size="sm" icon="calendar-days">
                                                View Slots
                                            </flux:button>
                                        @endif

                                        <flux:button wire:click="openCustomBooking({{ $product->id }})"
                                            variant="filled" size="sm" icon="chat-bubble-left-right"
                                            :disabled="!$this->canMakeBooking">
                                            Custom Booking
                                        </flux:button>
                                    </div>
                                </div>

                                <div class="text-right ml-6">
                                    <flux:text size="xs" class="text-zinc-500 uppercase tracking-wide block mb-1">
                                        Starting at</flux:text>
                                    <flux:heading class="text-accent">{{ formatMoney($product->base_price, $workspace) }}
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
                            @if (($this->selectedProduct?->available_slots_count ?? 0) > 0)
                                @if ($this->hasSlots)
                                    Select your preferred time slots
                                @else
                                    Click "View Slots" on the selected service to load available dates
                                @endif
                            @else
                                This service accepts custom booking proposals only
                            @endif
                        @else
                            Choose a service above to view booking options
                        @endif
                    </flux:text>
                </div>

                @if ($selectedProductId && !$this->canMakeBooking && $this->bookingRestrictionMessage)
                    <div class="mb-6">
                        <flux:text size="sm" class="text-amber-700 dark:text-amber-300">
                            {{ $this->bookingRestrictionMessage }}
                        </flux:text>
                    </div>
                @endif

                @if ($selectedProductId)
                    @if (($this->selectedProduct?->available_slots_count ?? 0) > 0)
                        @if ($this->hasSlots)
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
                                                        {{ formatMoney($slot->price, $workspace) }}
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
                                @if ($this->canMakeBooking)
                                    <flux:button wire:click="openDrawer('inquiry')" variant="filled" size="sm"
                                        icon="chat-bubble-left-right">
                                        Request Custom Collaboration
                                    </flux:button>
                                @else
                                <flux:tooltip content="{{ $this->bookingRestrictionMessage }}" >
                                    <flux:button disabled variant="filled" size="sm">
                                        Booking Unavailable
                                    </flux:button>

                                </flux:tooltip>
                                @endif
                            </div>
                        @else
                            <div
                                class="py-16 text-center border-2 border-dashed border-zinc-300 dark:border-zinc-600 rounded-xl">
                                <flux:icon.cursor-arrow-rays class="w-12 h-12 mx-auto text-zinc-400 mb-4" />
                                <flux:text class="text-zinc-500 max-w-md mx-auto mb-6">
                                    Click "View Slots" in the selected service card to see available dates.
                                </flux:text>
                            </div>
                        @endif
                    @else
                        <div
                            class="text-center py-16 border-2 border-dashed border-zinc-300 dark:border-zinc-600 rounded-xl">
                            <flux:icon.chat-bubble-left-right class="w-12 h-12 mx-auto text-zinc-400 mb-4" />
                            <flux:heading size="md" class="mb-2">Custom Proposals Only</flux:heading>
                            <flux:text class="text-zinc-500 max-w-md mx-auto mb-6">
                                This creator hand-picks collaborations to ensure perfect brand alignment.
                            </flux:text>

                            @if ($this->canMakeBooking)
                                <flux:button wire:click="openCustomBooking({{ $selectedProductId }})" variant="filled"
                                    size="sm" icon="chat-bubble-left-right">
                                    Custom Booking
                                </flux:button>
                            @else
                                <flux:button disabled variant="filled" size="sm">
                                    Booking Unavailable
                                </flux:button>
                            @endif
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

    @if ($selectedProductId && $this->hasSlots && count($selectedSlots) > 0 && $this->canMakeBooking)
        <div class="fixed bottom-6 left-1/2 -translate-x-1/2 w-full max-w-md px-4 z-40">
            <div class="bg-accent text-white p-4 rounded-xl shadow-2xl flex items-center justify-between">
                <div>
                    <span class="block text-xs font-semibold opacity-90">{{ count($selectedSlots) }} date(s)
                        selected</span>
                    <span class="text-lg font-bold">{{ formatMoney($this->totalAmount, $workspace) }}</span>
                </div>
                <flux:button wire:click="openDrawer('instant')">
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
                                <flux:text class="font-bold text-accent">{{ formatMoney($slot->price, $workspace) }}
                                </flux:text>
                            </div>
                        @endforeach
                    </div>

                    <div class="border-t border-zinc-200 dark:border-zinc-700 pt-4 mb-8">
                        <div class="flex justify-between items-center">
                            <flux:text class="font-semibold">Total Amount</flux:text>
                            <flux:heading class="text-accent">{{ formatMoney($this->totalAmount, $workspace) }}
                            </flux:heading>
                        </div>
                    </div>
                </div>
            @endif

            <div class="space-y-8 flex-1">
                @if ($this->isGuestUser)
                    <div class="space-y-6">
                        <flux:heading size="md" class="text-accent">Brand Information</flux:heading>

                        <div class="space-y-4">
                            <flux:input wire:model="guestData.name" label="Full Name" placeholder="Your full name"
                                required />
                            <flux:input wire:model="guestData.email" label="Email Address" type="email"
                                placeholder="your@email.com" required />

                            <div class="grid grid-cols-1 gap-4">
                                <flux:input wire:model="guestData.company" label="Company Name"
                                    placeholder="Your company or brand" />
                            </div>
                        </div>
                    </div>
                @endif

                @if ($checkoutType === 'inquiry')
                    <div class="space-y-6">
                        <flux:heading size="md" class="text-accent">Campaign Details</flux:heading>

                        <div class="space-y-5">
                            @if (! $this->isGuestUser)
                                @if ($this->hasBrandCampaignOptions)
                                    <flux:field>
                                        <flux:label>Save inquiry to</flux:label>
                                        <div class="mt-2 grid gap-3 sm:grid-cols-2">
                                            <label @class([
                                                'flex cursor-pointer flex-col gap-1 rounded-lg border p-4 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/60',
                                                'border-accent bg-accent/5' => $campaignSelectionMode === 'new',
                                                'border-zinc-200 dark:border-zinc-700' => $campaignSelectionMode !== 'new',
                                            ])>
                                                <div class="flex items-center gap-2">
                                                    <input
                                                        type="radio"
                                                        wire:model.live="campaignSelectionMode"
                                                        value="new"
                                                        class="accent-(--color-accent)"
                                                    />
                                                    <span class="font-medium">New Campaign</span>
                                                </div>
                                                <p class="pl-5 text-xs text-zinc-500">Create a new private campaign for this inquiry.</p>
                                            </label>

                                            <label @class([
                                                'flex cursor-pointer flex-col gap-1 rounded-lg border p-4 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/60',
                                                'border-accent bg-accent/5' => $campaignSelectionMode === 'existing',
                                                'border-zinc-200 dark:border-zinc-700' => $campaignSelectionMode !== 'existing',
                                            ])>
                                                <div class="flex items-center gap-2">
                                                    <input
                                                        type="radio"
                                                        wire:model.live="campaignSelectionMode"
                                                        value="existing"
                                                        class="accent-(--color-accent)"
                                                    />
                                                    <span class="font-medium">Existing Campaign</span>
                                                </div>
                                                <p class="pl-5 text-xs text-zinc-500">Attach this inquiry to one of your existing campaigns.</p>
                                            </label>
                                        </div>
                                        <flux:error name="campaignSelectionMode" />
                                    </flux:field>

                                    @if ($campaignSelectionMode === 'existing')
                                        <flux:field>
                                            <flux:label>Select campaign</flux:label>
                                            <flux:select wire:model.live="selectedCampaignId">
                                                <option value="">Select your campaign</option>
                                                @foreach ($this->brandCampaignOptions as $campaign)
                                                    <option value="{{ $campaign->id }}">
                                                        {{ $campaign->title }} • {{ $campaign->status->label() }} • {{ $campaign->is_public ? 'Public' : 'Private' }}
                                                    </option>
                                                @endforeach
                                            </flux:select>
                                            <flux:error name="selectedCampaignId" />
                                        </flux:field>
                                    @endif
                                @endif
                            @endif

                            @if (! $this->usingExistingCampaignForInquiry)
                                @foreach ($this->inquirySkeletonSchema['metadata'] as $metaField)
                                    <flux:field wire:key="inquiry-meta-{{ $metaField['key'] }}">
                                        <flux:label>
                                            {{ $metaField['label'] }}
                                            @if ($metaField['required'])
                                                *
                                            @endif
                                        </flux:label>

                                        <flux:input
                                            wire:model="guestData.{{ $metaField['key'] }}"
                                            type="{{ $metaField['type'] === 'number' ? 'number' : 'text' }}"
                                            :readonly="(bool) ($metaField['readonly'] ?? false)"
                                            :step="$metaField['type'] === 'number' ? '0.01' : null"
                                        />

                                        <flux:description>{{ $metaField['help'] }}</flux:description>
                                        <flux:error name="guestData.{{ $metaField['key'] }}" />
                                    </flux:field>
                                @endforeach

                                @foreach ($this->inquirySkeletonSchema['sections'] as $section)
                                        <flux:heading size="sm" class="mb-4">{{ $section['title'] }}</flux:heading>

                                        <div class="space-y-4">
                                            @foreach ($section['fields'] as $field)
                                                <flux:field wire:key="inquiry-field-{{ $field['key'] }}">
                                                    <flux:label>
                                                        {{ $field['label'] }}
                                                        @if ($field['required'])
                                                            *
                                                        @endif
                                                    </flux:label>

                                                    @if ($field['type'] === 'textarea')
                                                        <flux:textarea
                                                            wire:model="guestData.{{ $field['key'] }}"
                                                            rows="4"
                                                            placeholder="{{ $field['placeholder'] ?? '' }}"
                                                        />
                                                    @elseif ($field['type'] === 'select')
                                                        <flux:select wire:model="guestData.{{ $field['key'] }}">
                                                            <option value="">Select an option</option>
                                                            @foreach (($field['options'] ?? []) as $option)
                                                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                                            @endforeach
                                                        </flux:select>
                                                    @else
                                                        <flux:input
                                                            wire:model="guestData.{{ $field['key'] }}"
                                                            type="{{ $field['key'] === 'product_service_link' ? 'url' : 'text' }}"
                                                            placeholder="{{ $field['placeholder'] ?? '' }}"
                                                        />
                                                    @endif

                                                    <flux:error name="guestData.{{ $field['key'] }}" />
                                                </flux:field>
                                            @endforeach
                                        </div>
                                @endforeach
                            @else
                                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50/70 dark:bg-zinc-900/50 p-4 space-y-4">
                                    @if ($this->selectedBrandCampaign)
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <div class="rounded-md border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-3">
                                                <flux:text size="xs" class="text-zinc-500">Campaign</flux:text>
                                                <flux:text class="font-medium">{{ $this->selectedBrandCampaign->title }}</flux:text>
                                            </div>
                                            <div class="rounded-md border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-3">
                                                <flux:text size="xs" class="text-zinc-500">Budget</flux:text>
                                                <flux:text class="font-medium">{{ formatMoney((float) $this->selectedBrandCampaign->total_budget, $this->brandWorkspace) }}</flux:text>
                                            </div>
                                            <div class="rounded-md border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-3">
                                                <flux:text size="xs" class="text-zinc-500">Status</flux:text>
                                                <flux:text class="font-medium">{{ $this->selectedBrandCampaign->status->label() }}</flux:text>
                                            </div>
                                            <div class="rounded-md border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-3">
                                                <flux:text size="xs" class="text-zinc-500">Visibility</flux:text>
                                                <flux:text class="font-medium">{{ $this->selectedBrandCampaign->is_public ? 'Public' : 'Private' }}</flux:text>
                                            </div>
                                        </div>

                                        @php
                                            $campaignBrief = is_array($this->selectedBrandCampaign->content_brief)
                                                ? $this->selectedBrandCampaign->content_brief
                                                : [];
                                            $campaignSchema = data_get($campaignBrief, '_form_schema.sections', []);
                                        @endphp

                                        @if (! empty($campaignSchema))
                                            <div class="space-y-4">
                                                @foreach ($campaignSchema as $section)
                                                    <div class="rounded-md border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-3">
                                                        <flux:text class="font-semibold mb-2">{{ data_get($section, 'title', 'Campaign Section') }}</flux:text>
                                                        <div class="space-y-2">
                                                            @foreach ((array) data_get($section, 'fields', []) as $field)
                                                                @php
                                                                    $fieldName = data_get($field, 'name');
                                                                    $value = $fieldName ? data_get($campaignBrief, $fieldName) : null;
                                                                @endphp
                                                                <div>
                                                                    <flux:text size="xs" class="text-zinc-500">{{ data_get($field, 'label', 'Field') }}</flux:text>
                                                                    <flux:text>{{ filled($value) ? (is_array($value) ? implode(', ', $value) : $value) : 'Not set' }}</flux:text>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    @else
                                        <flux:text size="sm" class="text-zinc-500">Select a campaign to view a preview.</flux:text>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                @if ($checkoutType === 'instant' && $this->selectedProduct && $this->selectedProduct->requirements->count() > 0)
                    <div class="space-y-6">
                        <flux:heading size="md" class="text-accent">Project Requirements</flux:heading>
                        <x-bookings.requirements-form :requirements="$this->selectedProduct->requirements" :empty-state="false" />
                    </div>
                @endif
            </div>

            <div class="mt-auto pt-6">
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
