<?php

use App\Enums\BookingStatus;
use App\Enums\BookingType;
use App\Enums\SlotStatus;
use App\Models\Booking;
use App\Models\Product;
use App\Models\Slot;
use App\Models\Workspace;
use App\Services\BookingService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app'), Title('New Booking')] class extends Component {

    public int $step = 1;

    public function mount(): void
    {
        if (isCreatorWorkspace()) {
            $products = currentWorkspace()->products()->where('is_active', true)->get();

            if ($products->count() === 1) {
                $this->creatorProductId = $products->first()->id;
                $this->creatorAmount = (string) $products->first()->base_price;
            }
        }

        if (isBrandWorkspace()) {
            $workspace = currentWorkspace();
            $creators = Booking::where('brand_workspace_id', $workspace->id)
                ->whereNotNull('workspace_id')
                ->with('workspace')
                ->get()
                ->pluck('workspace')
                ->filter()
                ->unique('id')
                ->values();

            if ($creators->count() === 1) {
                $this->selectedCreatorWorkspaceId = $creators->first()->id;

                $products = Product::where('workspace_id', $this->selectedCreatorWorkspaceId)
                    ->where('is_active', true)
                    ->where('is_public', true)
                    ->get();

                if ($products->count() === 1) {
                    $this->brandProductId = $products->first()->id;
                }
            }
        }
    }

    // === Creator-initiated flow ===
    public ?int $creatorProductId = null;
    public string $creatorAmount = '';
    public string $creatorNotes = '';
    public string $brandType = 'new'; // 'new' | 'existing'
    public ?int $existingBrandWorkspaceId = null;
    public string $brandName = '';
    public string $brandEmail = '';
    public string $brandCompany = '';

    // Creator done state
    public bool $bookingCreated = false;
    public string $inviteUrl = '';
    public int $createdBookingId = 0;
    public bool $emailSent = false;

    // === Brand-initiated flow ===
    public ?int $selectedCreatorWorkspaceId = null;
    public ?int $brandProductId = null;
    public string $bookingMode = ''; // 'slot' | 'inquiry'
    public ?int $selectedSlotId = null;
    public array $requirementData = [];
    public string $inquiryBudget = '';
    public string $campaignGoals = '';
    public string $pitch = '';

    #[Computed]
    public function isCreator(): bool
    {
        return isCreatorWorkspace();
    }

    #[Computed]
    public function isBrand(): bool
    {
        return isBrandWorkspace();
    }

    // === Creator computed ===

    #[Computed]
    public function creatorProducts(): \Illuminate\Database\Eloquent\Collection
    {
        return currentWorkspace()->products()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function previousBrands(): \Illuminate\Support\Collection
    {
        $workspace = currentWorkspace();

        return Booking::where('workspace_id', $workspace->id)
            ->whereNotNull('brand_workspace_id')
            ->with('brandWorkspace')
            ->get()
            ->pluck('brandWorkspace')
            ->filter()
            ->unique('id')
            ->values();
    }

    #[Computed]
    public function selectedCreatorProduct(): ?Product
    {
        if (! $this->creatorProductId) {
            return null;
        }

        return $this->creatorProducts->firstWhere('id', $this->creatorProductId);
    }

    // === Brand computed ===

    #[Computed]
    public function workedWithCreators(): \Illuminate\Support\Collection
    {
        $workspace = currentWorkspace();

        return Booking::where('brand_workspace_id', $workspace->id)
            ->whereNotNull('workspace_id')
            ->with('workspace.owner')
            ->get()
            ->pluck('workspace')
            ->filter()
            ->unique('id')
            ->values();
    }

    #[Computed]
    public function brandCreatorProducts(): \Illuminate\Database\Eloquent\Collection
    {
        if (! $this->selectedCreatorWorkspaceId) {
            return collect();
        }

        return Product::where('workspace_id', $this->selectedCreatorWorkspaceId)
            ->where('is_active', true)
            ->where('is_public', true)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function selectedBrandProduct(): ?Product
    {
        if (! $this->brandProductId) {
            return null;
        }

        return $this->brandCreatorProducts->firstWhere('id', $this->brandProductId)
            ?->load('requirements');
    }

    #[Computed]
    public function availableSlots(): \Illuminate\Database\Eloquent\Collection
    {
        if (! $this->brandProductId) {
            return collect();
        }

        return Slot::where('product_id', $this->brandProductId)
            ->where('status', SlotStatus::Available)
            ->whereDate('slot_date', '>=', now())
            ->orderBy('slot_date')
            ->orderBy('slot_time')
            ->get();
    }

    #[Computed]
    public function selectedCreatorWorkspace(): ?Workspace
    {
        if (! $this->selectedCreatorWorkspaceId) {
            return null;
        }

        return Workspace::find($this->selectedCreatorWorkspaceId);
    }

    #[Computed]
    public function creatorCurrencySymbol(): string
    {
        $currency = $this->selectedCreatorWorkspace?->currency ?? 'USD';
        $currencies = \App\Support\CurrencySupport::getSupportedCurrencies();

        return $currencies[$currency]['symbol'] ?? $currency;
    }

    #[Computed]
    public function creatorCurrencyCode(): string
    {
        return $this->selectedCreatorWorkspace?->currency ?? 'USD';
    }

    // === Watchers ===

    public function updatedSelectedCreatorWorkspaceId(): void
    {
        $this->brandProductId = null;
        $this->bookingMode = '';
        $this->selectedSlotId = null;
        $this->requirementData = [];
        unset($this->brandCreatorProducts, $this->selectedBrandProduct, $this->availableSlots, $this->selectedCreatorWorkspace, $this->creatorCurrencySymbol, $this->creatorCurrencyCode);

        if ($this->selectedCreatorWorkspaceId) {
            $products = Product::where('workspace_id', $this->selectedCreatorWorkspaceId)
                ->where('is_active', true)
                ->where('is_public', true)
                ->get();

            if ($products->count() === 1) {
                $this->brandProductId = $products->first()->id;
            }
        }
    }

    public function updatedBrandProductId(): void
    {
        $this->bookingMode = '';
        $this->selectedSlotId = null;
        $this->requirementData = [];
        unset($this->selectedBrandProduct, $this->availableSlots);
    }

    public function updatedCreatorProductId(): void
    {
        $product = Product::find($this->creatorProductId);
        $this->creatorAmount = $product ? (string) $product->base_price : '';
        unset($this->selectedCreatorProduct);
    }

    // === Navigation ===

    public function nextStep(): void
    {
        if ($this->isCreator) {
            $this->nextStepCreator();
        } else {
            $this->nextStepBrand();
        }
    }

    private function nextStepCreator(): void
    {
        if ($this->step === 1) {
            $this->validate([
                'creatorProductId' => 'required|numeric|min:1',
                'creatorAmount' => 'required|numeric|min:1',
            ]);
            $this->step = 2;

            return;
        }

        if ($this->step === 2) {
            if ($this->brandType === 'existing') {
                $this->validate(['existingBrandWorkspaceId' => 'required|integer']);
            } else {
                $this->validate([
                    'brandName' => 'required|string|max:255',
                    'brandEmail' => 'required|email|max:255',
                    'brandCompany' => 'nullable|string|max:255',
                ]);
            }
            $this->step = 3;
        }
    }

    private function nextStepBrand(): void
    {
        if ($this->step === 1) {
            $this->validate(['selectedCreatorWorkspaceId' => 'required|integer']);
            $this->step = 2;

            return;
        }

        if ($this->step === 2) {
            $this->validate([
                'brandProductId' => 'required|integer',
                'bookingMode' => 'required|in:slot,inquiry',
            ]);
            $this->step = 3;
        }
    }

    public function prevStep(): void
    {
        if ($this->step > 1) {
            $this->step--;
        }
    }

    // === Creator: submit ===

    public function createBooking(): void
    {
        $this->validate([
            'creatorProductId' => 'required|numeric|min:1',
            'creatorAmount' => 'required|numeric|min:1',
        ]);

        $data = [
            'creator_workspace' => currentWorkspace(),
            'product_id' => $this->creatorProductId,
            'amount' => (float) $this->creatorAmount,
            'notes' => $this->creatorNotes ?: null,
            'brand_type' => $this->brandType,
        ];

        if ($this->brandType === 'existing') {
            $data['brand_workspace_id'] = $this->existingBrandWorkspaceId;
        } else {
            $data['brand_email'] = $this->brandEmail;
            $data['brand_name'] = $this->brandName;
            $data['brand_company'] = $this->brandCompany ?: null;
        }

        $result = app(BookingService::class)->createCreatorInitiatedBooking($data);

        if ($result['success']) {
            $this->bookingCreated = true;
            $this->inviteUrl = $result['invite_url'];
            $this->createdBookingId = $result['booking_id'];
            $this->step = 4;
        } else {
            $this->dispatch('error', $result['error']);
        }
    }

    public function sendInviteEmail(): void
    {
        if (! $this->bookingCreated || ! $this->inviteUrl) {
            return;
        }

        $booking = Booking::find($this->createdBookingId);

        if (! $booking) {
            return;
        }

        $email = $this->brandType === 'existing'
            ? optional(Workspace::find($this->existingBrandWorkspaceId)?->owner)->email
            : $this->brandEmail;

        if ($email) {
            app(BookingService::class)->sendBookingInviteEmail($booking, $email, $this->inviteUrl);
            $this->emailSent = true;
            $this->dispatch('success', 'Invite email sent!');
        }
    }

    // === Brand instant: submit ===

    public function bookSlot(): void
    {
        if (! $this->selectedSlotId) {
            $this->addError('selectedSlotId', 'Please select a slot.');

            return;
        }

        $product = $this->selectedBrandProduct?->load('requirements');

        if ($product) {
            foreach ($product->requirements->where('is_required', true) as $req) {
                if (empty($this->requirementData[$req->id])) {
                    $this->addError("requirementData.{$req->id}", 'This field is required.');

                    return;
                }
            }
        }

        $workspace = currentWorkspace();

        $result = app(BookingService::class)->createBrandInstantBooking([
            'creator_workspace_id' => $this->selectedCreatorWorkspaceId,
            'slot_ids' => [$this->selectedSlotId],
            'requirement_data' => $this->requirementData,
            'brand_user_id' => Auth::id(),
            'brand_workspace_id' => $workspace->id,
        ]);

        if ($result['success']) {
            $this->redirect($result['checkout_url'], navigate: false);
        } else {
            $this->dispatch('error', $result['error']);
        }
    }

    // === Brand inquiry: submit ===

    public function submitInquiry(): void
    {
        $this->validate([
            'inquiryBudget' => 'required|numeric|min:1',
            'campaignGoals' => 'required|string',
            'pitch' => 'required|string',
        ]);

        $workspace = currentWorkspace();

        $result = app(BookingService::class)->createBrandInquiry([
            'creator_workspace_id' => $this->selectedCreatorWorkspaceId,
            'product_id' => $this->brandProductId,
            'budget' => (float) $this->inquiryBudget,
            'campaign_goals' => $this->campaignGoals,
            'pitch' => $this->pitch,
            'brand_user_id' => Auth::id(),
            'brand_workspace_id' => $workspace->id,
        ]);

        if ($result['success']) {
            $this->step = 4;
            $this->dispatch('success', 'Inquiry submitted! The creator will review your proposal.');
        } else {
            $this->dispatch('error', $result['error']);
        }
    }
}; ?>

<div>
    <div class="mb-8 flex items-center justify-between">
        <div>
            <flux:heading size="xl">New Booking</flux:heading>
            <flux:subheading>
                @if($this->isCreator)
                    Create a booking for a brand you're working with
                @else
                    Book a creator you've worked with
                @endif
            </flux:subheading>
        </div>
        <flux:button href="{{ route('bookings.index') }}" variant="ghost" icon="arrow-left">
            Back to Bookings
        </flux:button>
    </div>

    {{-- Progress Steps --}}
    @if($step < 4)
        <div class="mb-8">
            <div class="flex items-center gap-2">
                @php
                    $totalSteps = $this->isCreator ? 3 : 3;
                    $stepLabels = $this->isCreator
                        ? ['Product & Price', 'Brand Details', 'Confirm']
                        : ['Select Creator', 'Product & Type', $bookingMode === 'slot' ? 'Slot & Requirements' : 'Campaign Details'];
                @endphp
                @foreach($stepLabels as $i => $label)
                    @php $stepNum = $i + 1; @endphp
                    <div class="flex items-center {{ $stepNum < $totalSteps ? 'flex-1' : '' }}">
                        <div class="flex items-center gap-2">
                            <div @class([
                                'flex h-8 w-8 items-center justify-center rounded-full text-sm font-semibold',
                                'bg-accent text-accent-foreground' => $step > $stepNum,
                                'bg-accent/20 text-accent-content ring-2 ring-accent' => $step === $stepNum,
                                'bg-zinc-100 text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500' => $step < $stepNum,
                            ])>
                                @if($step > $stepNum)
                                    <flux:icon.check class="h-4 w-4" />
                                @else
                                    {{ $stepNum }}
                                @endif
                            </div>
                            <span @class([
                                'text-sm font-medium',
                                'text-accent-content' => $step === $stepNum,
                                'text-zinc-400 dark:text-zinc-500' => $step !== $stepNum,
                            ])>{{ $label }}</span>
                        </div>
                        @if($stepNum < $totalSteps)
                            <div class="mx-3 h-px flex-1 bg-zinc-200 dark:bg-zinc-700"></div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="mx-auto max-w-2xl">

        {{-- ======================= CREATOR FLOW ======================= --}}
        @if($this->isCreator)

            @if($step === 1)
                <div class="rounded-xl border border-zinc-200 bg-white p-8 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                    <flux:heading size="lg" class="mb-6">Product & Price</flux:heading>

                    <div class="space-y-5">
                        <flux:field>
                            <flux:label>Product *</flux:label>
                            <flux:select  wire:model.live="creatorProductId" placeholder="Select a product...">
                                @foreach($this->creatorProducts as $product)
                                    <flux:select.option value="{{ $product->id }}">
                                        {{ $product->name }} — {{ formatMoney($product->base_price) }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="creatorProductId" />
                        </flux:field>

                        @if($this->selectedCreatorProduct)
                            <flux:callout variant="info" icon="information-circle">
                                <flux:callout.text>Base price: <strong>{{ formatMoney($this->selectedCreatorProduct->base_price) }}</strong>. You can adjust below.</flux:callout.text>
                            </flux:callout>
                        @endif

                        <flux:field>
                            <flux:label>Amount *</flux:label>
                            <flux:input
                                wire:model="creatorAmount"
                                type="number"
                                min="1"
                                step="0.01"
                            />
                            <flux:description>The amount this brand will pay for this collaboration.</flux:description>
                            <flux:error name="creatorAmount" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Note for brand (Optional)</flux:label>
                            <flux:textarea wire:model="creatorNotes" rows="3" placeholder="e.g. Excited to work with you on this campaign…" />
                        </flux:field>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <flux:button wire:click="nextStep" variant="primary" icon-trailing="arrow-right">
                            Continue
                        </flux:button>
                    </div>
                </div>
            @endif

            @if($step === 2)
                <div class="rounded-xl border border-zinc-200 bg-white p-8 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                    <flux:heading size="lg" class="mb-6">Brand Details</flux:heading>

                    <div class="space-y-6">
                        @if($this->previousBrands->isNotEmpty())
                            <flux:radio.group wire:model.live="brandType" label="Brand type">
                                <flux:radio value="existing" label="Existing brand" description="A brand you've already worked with" />
                                <flux:radio value="new" label="New brand" description="Enter their contact details manually" />
                            </flux:radio.group>
                        @endif

                        @if($brandType === 'existing' && $this->previousBrands->isNotEmpty())
                            <flux:field>
                                <flux:label>Select brand *</flux:label>
                                <flux:select wire:model="existingBrandWorkspaceId" placeholder="Choose a brand…">
                                    @foreach($this->previousBrands as $brand)
                                        <flux:select.option value="{{ $brand->id }}">{{ $brand->name }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="existingBrandWorkspaceId" />
                            </flux:field>
                        @else
                            <flux:field>
                                <flux:label>Contact name *</flux:label>
                                <flux:input wire:model="brandName" placeholder="Jane Smith" />
                                <flux:error name="brandName" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Email *</flux:label>
                                <flux:input wire:model="brandEmail" type="email" placeholder="jane@brand.com" />
                                <flux:description>We'll use this to send them the payment link.</flux:description>
                                <flux:error name="brandEmail" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Company (Optional)</flux:label>
                                <flux:input wire:model="brandCompany" placeholder="Acme Corp" />
                            </flux:field>
                        @endif
                    </div>

                    <div class="mt-6 flex gap-3">
                        <flux:button wire:click="prevStep" variant="ghost" icon="arrow-left">Back</flux:button>
                        <flux:spacer />
                        <flux:button wire:click="nextStep" variant="primary" icon-trailing="arrow-right">
                            Continue
                        </flux:button>
                    </div>
                </div>
            @endif

            @if($step === 3)
                <div class="rounded-xl border border-zinc-200 bg-white p-8 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                    <flux:heading size="lg" class="mb-6">Confirm Booking</flux:heading>

                    <div class="mb-6 space-y-4 rounded-lg border border-zinc-100 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-center justify-between">
                            <flux:text class="text-sm text-zinc-500">Product</flux:text>
                            <flux:text class="font-medium">{{ $this->selectedCreatorProduct?->name }}</flux:text>
                        </div>
                        <div class="flex items-center justify-between">
                            <flux:text class="text-sm text-zinc-500">Amount</flux:text>
                            <flux:text class="font-semibold text-accent-content">{{ formatMoney((float) $creatorAmount) }}</flux:text>
                        </div>
                        <div class="flex items-center justify-between">
                            <flux:text class="text-sm text-zinc-500">Brand</flux:text>
                            <flux:text class="font-medium">
                                @if($brandType === 'existing')
                                    {{ $this->previousBrands->firstWhere('id', $existingBrandWorkspaceId)?->name ?? '—' }}
                                @else
                                    {{ $brandName }} {{ $brandCompany ? "($brandCompany)" : '' }}
                                @endif
                            </flux:text>
                        </div>
                        @if($creatorNotes)
                            <div>
                                <flux:text class="text-sm text-zinc-500">Note</flux:text>
                                <flux:text class="mt-1 text-sm">{{ $creatorNotes }}</flux:text>
                            </div>
                        @endif
                    </div>

                    @if($brandType === 'existing')
                        <flux:callout variant="info" icon="bell">
                            <flux:callout.text>The brand will receive a notification with their payment link.</flux:callout.text>
                        </flux:callout>
                    @else
                        <flux:callout variant="warning" icon="link">
                            <flux:callout.text>After creating, you'll get an invite link to share with the brand.</flux:callout.text>
                        </flux:callout>
                    @endif

                    <div class="mt-6 flex gap-3">
                        <flux:button wire:click="prevStep" variant="ghost" icon="arrow-left">Back</flux:button>
                        <flux:spacer />
                        <flux:button
                            wire:click="createBooking"
                            variant="primary"
                            icon="check"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-75"
                        >
                            <span wire:loading.remove wire:target="createBooking">Create Booking</span>
                            <span wire:loading wire:target="createBooking">Creating…</span>
                        </flux:button>
                    </div>
                </div>
            @endif

            @if($step === 4 && $bookingCreated)
                <div class="rounded-xl border text-center border-zinc-200 bg-white p-8 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-green-100 dark:bg-green-900">
                        <flux:icon.check-circle class="h-7 w-7 text-green-600 dark:text-green-400" />
                    </div>
                    <flux:heading size="xl" class="mb-2">Booking Created!</flux:heading>
                    <flux:text class="mb-6 text-zinc-500">Share the link below with the brand so they can review and complete payment.</flux:text>

                    <div class="mb-4 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
                        <flux:text class="mb-2 text-xs font-medium uppercase tracking-wide text-zinc-400">Invite Link</flux:text>
                        <div class="flex items-center gap-2">
                            <code class="flex-1 truncate rounded bg-zinc-50 px-3 py-2 text-sm text-zinc-800 dark:bg-zinc-900 dark:text-zinc-200">{{ $inviteUrl }}</code>
                            <flux:button
                                size="sm"
                                icon="clipboard"
                                x-on:click="window.navigator.clipboard.writeText('{{ $inviteUrl }}').then(() => $dispatch('success', 'Link copied!'))"
                            >
                                Copy
                            </flux:button>
                        </div>
                    </div>

                    @if($brandType === 'new' && $brandEmail && ! $emailSent)
                        <div class="mb-4">
                            <flux:button
                                wire:click="sendInviteEmail"
                                variant="filled"
                                icon="envelope"
                                wire:loading.attr="disabled"
                            >
                                <span wire:loading.remove wire:target="sendInviteEmail">Send invite email to {{ $brandEmail }}</span>
                                <span wire:loading wire:target="sendInviteEmail">Sending…</span>
                            </flux:button>
                        </div>
                    @endif

                    @if($emailSent)
                        <flux:callout variant="success" icon="check-circle" class="mb-4 text-left">
                            <flux:callout.text>Invite email sent to {{ $brandEmail }}</flux:callout.text>
                        </flux:callout>
                    @endif

                    <div class="flex justify-center gap-3">
                        <flux:button :href="route('bookings.show', $createdBookingId)" variant="primary" icon="eye">
                            View Booking
                        </flux:button>
                        <flux:button :href="route('bookings.index')" variant="ghost">
                            Back to Bookings
                        </flux:button>
                    </div>
                </div>
            @endif

        {{-- ======================= BRAND FLOW ======================= --}}
        @elseif($this->isBrand)

            @if($step === 1)
                <div class="rounded-xl border border-zinc-200 bg-white p-8 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                    <flux:heading size="lg" class="mb-2">Select Creator</flux:heading>
                    <flux:text class="mb-6 text-zinc-500">You can only book creators you've previously worked with.</flux:text>

                    @if($this->workedWithCreators->isEmpty())
                        <div class="rounded-lg border-2 border-dashed border-zinc-300 p-8 text-center dark:border-zinc-600">
                            <flux:icon.user-group class="mx-auto mb-3 h-10 w-10 text-zinc-400" />
                            <flux:heading size="lg">No creators yet</flux:heading>
                            <flux:text class="mt-1 text-zinc-500">You haven't worked with any creators on this platform yet.</flux:text>
                        </div>
                    @else
                        <flux:field>
                            <flux:select wire:model.live="selectedCreatorWorkspaceId" placeholder="Choose a creator…">
                                @foreach($this->workedWithCreators as $creatorWs)
                                    <flux:select.option value="{{ $creatorWs->id }}">
                                        {{ $creatorWs->owner?->name ?? $creatorWs->name }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="selectedCreatorWorkspaceId" />
                        </flux:field>

                        <div class="mt-6 flex justify-end">
                            <flux:button wire:click="nextStep" variant="primary" icon-trailing="arrow-right" :disabled="! $selectedCreatorWorkspaceId">
                                Continue
                            </flux:button>
                        </div>
                    @endif
                </div>
            @endif

            @if($step === 2)
                <div class="rounded-xl border border-zinc-200 bg-white p-8 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                    <flux:heading size="lg" class="mb-6">Select Product & Type</flux:heading>

                    <div class="space-y-6">
                        <flux:field>
                            <flux:label>Product *</flux:label>
                            @if($this->brandCreatorProducts->isEmpty())
                                <flux:text class="text-sm text-zinc-500">No public products available from this creator.</flux:text>
                            @else
                                <flux:select wire:model.live="brandProductId" placeholder="Choose a product…">
                                    @foreach($this->brandCreatorProducts as $product)
                                        <flux:select.option value="{{ $product->id }}">
                                            {{ $product->name }} — {{ \App\Support\CurrencySupport::formatCurrency((float) $product->base_price, $this->creatorCurrencyCode) }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                            @endif
                            <flux:error name="brandProductId" />
                        </flux:field>

                        @if($brandProductId)
                            <flux:field>
                                <flux:label>Booking type *</flux:label>
                                <div class="mt-2 grid gap-3 sm:grid-cols-2">
                                    <label class="flex cursor-pointer flex-col gap-1 rounded-lg border p-4 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/60 {{ $bookingMode === 'slot' ? 'border-accent bg-accent/5' : 'border-zinc-200 dark:border-zinc-700' }}">
                                        <div class="flex items-center gap-2">
                                            <input type="radio" wire:model.live="bookingMode" value="slot" class="accent-[var(--color-accent)]" />
                                            <span class="font-medium">Instant Booking</span>
                                        </div>
                                        <p class="pl-5 text-xs text-zinc-500">Pick a slot, fill requirements, and pay right away.</p>
                                    </label>
                                    <label class="flex cursor-pointer flex-col gap-1 rounded-lg border p-4 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/60 {{ $bookingMode === 'inquiry' ? 'border-accent bg-accent/5' : 'border-zinc-200 dark:border-zinc-700' }}">
                                        <div class="flex items-center gap-2">
                                            <input type="radio" wire:model.live="bookingMode" value="inquiry" class="accent-[var(--color-accent)]" />
                                            <span class="font-medium">Custom Inquiry</span>
                                        </div>
                                        <p class="pl-5 text-xs text-zinc-500">Propose your budget and goals. Creator approves first.</p>
                                    </label>
                                </div>
                                <flux:error name="bookingMode" />
                            </flux:field>
                        @endif
                    </div>

                    <div class="mt-6 flex gap-3">
                        <flux:button wire:click="prevStep" variant="ghost" icon="arrow-left">Back</flux:button>
                        <flux:spacer />
                        <flux:button wire:click="nextStep" variant="primary" icon-trailing="arrow-right" :disabled="! $brandProductId || ! $bookingMode">
                            Continue
                        </flux:button>
                    </div>
                </div>
            @endif

            @if($step === 3 && $bookingMode === 'slot')
                <div class="rounded-xl border border-zinc-200 bg-white p-8 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                    <flux:heading size="lg" class="mb-2">Select Slot</flux:heading>

                    @if($this->availableSlots->isEmpty())
                        <flux:callout variant="warning" icon="calendar" class="mb-6">
                            <flux:callout.text>No available slots for this product. Please choose a different product or try a custom inquiry.</flux:callout.text>
                        </flux:callout>
                    @else
                        <div class="mb-6 space-y-2">
                            @foreach($this->availableSlots as $slot)
                                <label class="flex cursor-pointer items-center gap-4 rounded-lg border p-4 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/60 {{ $selectedSlotId == $slot->id ? 'border-accent bg-accent/5' : 'border-zinc-200 dark:border-zinc-700' }}">
                                    <input type="radio" wire:model.live="selectedSlotId" value="{{ $slot->id }}" class="accent-[var(--color-accent)]" />
                                    <div class="flex flex-1 items-center justify-between">
                                        <div>
                                            <p class="font-medium">{{ formatWorkspaceDate($slot->slot_date) }}</p>
                                            <p class="text-sm text-zinc-500">{{ formatWorkspaceTime($slot->slot_date) }}</p>
                                        </div>
                                        <flux:badge variant="ghost">{{ \App\Support\CurrencySupport::formatCurrency((float) $slot->price, $this->creatorCurrencyCode) }}</flux:badge>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    @endif

                    @if($selectedSlotId && $this->selectedBrandProduct?->requirements->isNotEmpty())
                        <div class="mb-6">
                            <flux:heading size="md" class="mb-4">Campaign Requirements</flux:heading>
                            <x-bookings.requirements-form :requirements="$this->selectedBrandProduct->requirements" :empty-state="false" />
                        </div>
                    @endif

                    <div class="flex gap-3">
                        <flux:button wire:click="prevStep" variant="ghost" icon="arrow-left">Back</flux:button>
                        <flux:spacer />
                        <flux:button
                            wire:click="bookSlot"
                            variant="primary"
                            icon-trailing="arrow-right"
                            :disabled="! $selectedSlotId"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-75"
                        >
                            <span wire:loading.remove wire:target="bookSlot">Proceed to Payment</span>
                            <span wire:loading wire:target="bookSlot">Preparing…</span>
                        </flux:button>
                    </div>
                </div>
            @endif

            @if($step === 3 && $bookingMode === 'inquiry')
                <div class="rounded-xl border border-zinc-200 bg-white p-8 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                    <flux:heading size="lg" class="mb-2">Campaign Details</flux:heading>
                    <flux:text class="mb-6 text-zinc-500">Tell the creator about your campaign. They'll review and approve before you proceed to payment.</flux:text>

                    <div class="space-y-5">
                        <flux:field>
                            <flux:label>Your budget *</flux:label>
                            <flux:input
                                wire:model="inquiryBudget"
                                type="number"
                                min="1"
                                step="0.01"
                                placeholder="500.00"
                                prefix="{{ $this->creatorCurrencySymbol }}"
                            />
                            <flux:description>
                                Amount in <strong>{{ $this->creatorCurrencyCode }}</strong> — the creator's currency.
                            </flux:description>
                            <flux:error name="inquiryBudget" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Campaign goals *</flux:label>
                            <flux:textarea wire:model="campaignGoals" rows="3" placeholder="e.g. Increase brand awareness for our new product launch targeting 18-30 year olds…" />
                            <flux:error name="campaignGoals" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Your pitch *</flux:label>
                            <flux:textarea wire:model="pitch" rows="4" placeholder="e.g. Hi! We're launching our new eco-friendly water bottle and think your audience would love it. We'd love you to create an authentic unboxing video…" />
                            <flux:description>Tell the creator why you want to work with them and what makes your campaign special.</flux:description>
                            <flux:error name="pitch" />
                        </flux:field>
                    </div>

                    <div class="mt-6 flex gap-3">
                        <flux:button wire:click="prevStep" variant="ghost" icon="arrow-left">Back</flux:button>
                        <flux:spacer />
                        <flux:button
                            wire:click="submitInquiry"
                            variant="primary"
                            icon="paper-airplane"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-75"
                        >
                            <span wire:loading.remove wire:target="submitInquiry">Send Inquiry</span>
                            <span wire:loading wire:target="submitInquiry">Sending…</span>
                        </flux:button>
                    </div>
                </div>
            @endif

            @if($step === 4)
                <div class="rounded-xl border border-green-200 bg-green-50 p-8 text-center shadow-sm dark:border-green-700 dark:bg-green-950">
                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-green-100 dark:bg-green-900">
                        <flux:icon.check-circle class="h-7 w-7 text-green-600 dark:text-green-400" />
                    </div>
                    <flux:heading size="xl" class="mb-2">Inquiry Submitted!</flux:heading>
                    <flux:text class="mb-6 text-zinc-500">Your proposal has been sent to the creator. They'll review and get back to you soon. Once approved, you'll fill in campaign requirements and complete payment.</flux:text>

                    <flux:button :href="route('bookings.index')" variant="primary">
                        View My Bookings
                    </flux:button>
                </div>
            @endif

        @else
            <flux:callout variant="danger" icon="exclamation-triangle">
                <flux:callout.text>You need to be using a creator or brand workspace to create a booking.</flux:callout.text>
            </flux:callout>
        @endif

    </div>
</div>
