<?php

use App\Models\Booking;
use App\Models\WorkspaceRating;
use App\Enums\BookingStatus;
use App\Enums\BookingType;
use App\Services\BookingService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app'), Title('Bookings')] class extends Component {
    use WithPagination;

    public $sortBy = 'created_at';
    public $sortDirection = 'desc';
    public $statusFilter = 'all';
    public $typeFilter = 'all';

    // Modal states — property names must match x-bookings.* component wire:model contracts
    public bool $showApproveModal = false;
    public bool $showRejectModal = false;
    public bool $showCounterModal = false;
    public bool $showRevisionForm = false;
    public bool $showDisputeForm = false;
    public bool $showRatingPrompt = false;
    public ?Booking $selectedBooking = null;
    public string $rejectionNote = '';
    public string $counterNote = '';
    public string $counterAmount = '';
    public string $revisionNotes = '';
    public string $disputeReason = '';

    public bool $ratingSubmitted = false;
    public int $ratingValue = 0;
    public array $selectedTags = [];
    public string $ratingComment = '';

    /** @var array<string> */
    public array $availableTags = ['Professional', 'Fast Delivery', 'Great Quality', 'Creative'];

    public function sort($column)
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function filterByStatus($status)
    {
        $this->statusFilter = $status;
        $this->resetPage();
    }

    public function filterByType($type)
    {
        $this->typeFilter = $type;
        $this->resetPage();
    }

    #[Computed]
    public function isBrand(): bool
    {
        return isBrandWorkspace();
    }

    #[Computed]
    public function bookings()
    {
        $workspace = currentWorkspace();

        $query = $this->isBrand
            ? Booking::where('brand_workspace_id', $workspace->id)
                ->with(['product.workspace', 'brandUser', 'slot', 'latestSubmission'])
            : $workspace->bookings()
                ->with(['product', 'brandUser', 'brandWorkspace', 'slot', 'latestSubmission']);

        return $query
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->typeFilter !== 'all', fn ($q) => $q->where('type', $this->typeFilter))
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(15);
    }

    public function confirmApprove(Booking $booking): void
    {
        $this->selectedBooking = $booking;
        $this->showApproveModal = true;
    }

    public function approveInquiry(): void
    {
        if (! $this->selectedBooking) {
            return;
        }

        $result = app(BookingService::class)->approveInquiry($this->selectedBooking);

        if ($result['success']) {
            $this->dispatch('success', 'Inquiry approved — the brand has been emailed a link to complete payment.');
        } else {
            $this->dispatch('error', $result['error']);
        }

        $this->resetModals();
    }

    public function confirmReject(Booking $booking): void
    {
        $this->selectedBooking = $booking;
        $this->showRejectModal = true;
    }

    /** Called by x-bookings.reject-inquiry-modal */
    public function rejectInquiry(): void
    {
        if (! $this->selectedBooking) {
            return;
        }

        $result = app(BookingService::class)->rejectInquiry($this->selectedBooking, $this->rejectionNote ?: null);

        if ($result['success']) {
            $this->dispatch('success', 'Inquiry rejected — the brand has been notified.');
        } else {
            $this->dispatch('error', $result['error']);
        }

        $this->resetModals();
    }

    public function confirmCounter(Booking $booking): void
    {
        $this->selectedBooking = $booking;
        $this->counterAmount = '';
        $this->counterNote = '';
        $this->showCounterModal = true;
    }

    /** Called by x-bookings.counter-inquiry-modal */
    public function counterInquiry(): void
    {
        $this->validate(['counterAmount' => 'required|numeric|min:1']);

        if (! $this->selectedBooking) {
            return;
        }

        $result = app(BookingService::class)->counterInquiry(
            $this->selectedBooking,
            (float) $this->counterAmount,
            $this->counterNote ?: null,
        );

        if ($result['success']) {
            $this->dispatch('success', 'Counter-offer sent — the brand has been notified.');
        } else {
            $this->dispatch('error', $result['error']);
        }

        $this->resetModals();
    }

    // === Brand: Work review actions ===

    public function confirmApproveWork(Booking $booking): void
    {
        $this->selectedBooking = $booking->load(['creator', 'product.workspace', 'latestSubmission']);
        $this->showApproveModal = true;
    }

    /** Called by x-bookings.approve-work-modal */
    public function approveWork(): void
    {
        if (! $this->selectedBooking) {
            return;
        }

        $result = app(BookingService::class)->approveWork($this->selectedBooking);

        if ($result['success']) {
            $this->selectedBooking->refresh()->load(['creator', 'product.workspace', 'latestSubmission']);
            $this->showApproveModal = false;

            if ($this->hasExistingRating($this->selectedBooking)) {
                $this->dispatch('success', 'Work approved! Payment will be released to the creator.');

                return;
            }

            $this->showRatingPrompt = true;
            $this->dispatch('success', 'Work approved! Payment will be released to the creator. You can now rate the creator.');
        } else {
            $this->dispatch('error', $result['error']);
            $this->resetModals();
        }
    }

    public function setRating(int $value): void
    {
        $this->ratingValue = $value;
    }

    public function toggleTag(string $tag): void
    {
        if (in_array($tag, $this->selectedTags, true)) {
            $this->selectedTags = array_values(array_filter($this->selectedTags, fn ($selectedTag) => $selectedTag !== $tag));

            return;
        }

        $this->selectedTags[] = $tag;
    }

    public function submitRating(?int $ratingValue = null, ?array $selectedTags = null, ?string $ratingComment = null): void
    {
        if ($ratingValue !== null) {
            $this->ratingValue = $ratingValue;
        }

        if ($selectedTags !== null) {
            $this->selectedTags = $selectedTags;
        }

        if ($ratingComment !== null) {
            $this->ratingComment = $ratingComment;
        }

        $this->validate(['ratingValue' => 'required|integer|min:1|max:5']);

        if (! $this->selectedBooking) {
            return;
        }

        $result = app(BookingService::class)->submitRating(
            $this->selectedBooking,
            $this->ratingValue,
            $this->selectedTags,
            $this->ratingComment ?: null,
        );

        if (! $result['success']) {
            $this->dispatch('error', $result['error']);

            return;
        }

        $this->ratingSubmitted = true;
        $this->showRatingPrompt = false;
        $this->dispatch('success', 'Thanks for the rating!');
        $this->resetRatingState();
        $this->selectedBooking = null;
    }

    public function skipRating(): void
    {
        $this->showRatingPrompt = false;
        $this->resetRatingState();
        $this->selectedBooking = null;
    }

    public function confirmRevision(Booking $booking): void
    {
        $this->selectedBooking = $booking->load(['latestSubmission']);
        $this->showRevisionForm = true;
    }

    /** Called by x-bookings.revision-request-modal */
    public function requestRevision(): void
    {
        $this->validate(['revisionNotes' => 'required|string|min:10']);

        if (! $this->selectedBooking) {
            return;
        }

        $result = app(BookingService::class)->requestRevision($this->selectedBooking, $this->revisionNotes);

        if ($result['success']) {
            $this->dispatch('success', 'Revision request sent to the creator.');
        } else {
            $this->dispatch('error', $result['error']);
        }

        $this->resetModals();
    }

    public function confirmDispute(Booking $booking): void
    {
        $this->selectedBooking = $booking;
        $this->showDisputeForm = true;
    }

    /** Called by x-bookings.dispute-modal */
    public function openDispute(): void
    {
        $this->validate(['disputeReason' => 'required|string|min:10']);

        if (! $this->selectedBooking) {
            return;
        }

        $result = app(BookingService::class)->openDispute($this->selectedBooking, $this->disputeReason);

        if ($result['success']) {
            $this->dispatch('success', 'Dispute opened. Our team will review within 48 hours.');
        } else {
            $this->dispatch('error', $result['error']);
        }

        $this->resetModals();
    }

    private function resetModals(): void
    {
        $this->showApproveModal = false;
        $this->showRejectModal = false;
        $this->showCounterModal = false;
        $this->showRevisionForm = false;
        $this->showDisputeForm = false;
        $this->showRatingPrompt = false;
        $this->selectedBooking = null;
        $this->rejectionNote = '';
        $this->counterNote = '';
        $this->counterAmount = '';
        $this->revisionNotes = '';
        $this->disputeReason = '';
        $this->resetRatingState();
    }

    private function hasExistingRating(Booking $booking): bool
    {
        return WorkspaceRating::query()
            ->where('booking_id', $booking->id)
            ->exists();
    }

    private function resetRatingState(): void
    {
        $this->ratingValue = 0;
        $this->selectedTags = [];
        $this->ratingComment = '';
    }
}; ?>

<div>
    <div class="mb-8 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Bookings</flux:heading>
            <flux:subheading>
                @if($this->isBrand)
                    Manage your bookings with creators
                @else
                    Manage your collaboration requests and bookings
                @endif
            </flux:subheading>
        </div>
        
        <div class="flex items-center gap-3">
            <flux:button href="{{ route('bookings.create') }}" variant="primary" icon="plus">New Booking</flux:button>
            <!-- Status Filter -->
            <flux:select wire:model.live="statusFilter" placeholder="All Status">
                <flux:select.option value="all">All Status</flux:select.option>
                @foreach(App\Enums\BookingStatus::cases() as $status)
                    <flux:select.option value="{{ $status->value }}">{{ $status->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            
            <!-- Type Filter -->
            <flux:select wire:model.live="typeFilter" placeholder="All Types">
                <flux:select.option value="all">All Types</flux:select.option>
                @foreach(App\Enums\BookingType::cases() as $type)
                    <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                @endforeach
            </flux:select>

        </div>
    </div>

    @if($this->bookings->count() > 0)
        <flux:table :paginate="$this->bookings">
            <flux:table.columns>
                <flux:table.column>
                    @if($this->isBrand) Creator @else Guest/Brand @endif
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'product_id'" :direction="$sortDirection" wire:click="sort('product_id')">Product</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'type'" :direction="$sortDirection" wire:click="sort('type')">Type</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'amount_paid'" :direction="$sortDirection" wire:click="sort('amount_paid')">Amount</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')">Status</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">Date</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->bookings as $booking)
                    <flux:table.row :key="$booking->id">
                        <flux:table.cell>
                            @if($this->isBrand)
                                <span class="font-medium text-zinc-800 dark:text-white">
                                    {{ $booking->product?->workspace?->name ?? '—' }}
                                </span>
                            @else
                                <div class="flex flex-col">
                                    <span class="font-medium text-zinc-800 dark:text-white">
                                        {{ $booking->brandUser?->name ?? $booking->guest_name }}
                                    </span>
                                    <span class="text-xs text-zinc-500">
                                        {{ $booking->brandUser?->email ?? $booking->guest_email }}
                                    </span>
                                    @if($booking->guest_company || $booking->brandWorkspace?->name)
                                        <span class="text-xs text-zinc-400">
                                            {{ $booking->guest_company ?? $booking->brandWorkspace?->name }}
                                        </span>
                                    @endif
                                </div>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="flex flex-col">
                                <span class="font-medium">{{ $booking->product->name }}</span>
                                @if($booking->slot)
                                    <span class="text-xs text-zinc-500">
                                        {{ formatWorkspaceDate($booking->slot->slot_date) }} - {{ formatWorkspaceTime($booking->slot->slot_date) }}
                                    </span>
                                @endif
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge size="sm" :color="$booking->type->badgeColor()" inset="top bottom">
                                {{ $booking->type->label() }}
                            </flux:badge>
                        </flux:table.cell>

                        <flux:table.cell variant="strong">
                            {{ $booking->formatAmount() }}
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge size="sm" :color="$booking->status->badgeColor()" inset="top bottom">
                                {{ $booking->status->label() }}
                            </flux:badge>
                        </flux:table.cell>

                        <flux:table.cell>
                            <span class="text-sm">{{ formatWorkspaceDate($booking->created_at) }}</span>
                            <span class="block text-xs text-zinc-500">{{ formatWorkspaceTime($booking->created_at) }}</span>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="flex justify-end">
                                <flux:dropdown>
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                    <flux:menu>
                                        <flux:menu.item :href="route('bookings.show', $booking)" icon="eye">View Details</flux:menu.item>

                                        @if($this->isBrand)
                                            @if($booking->canApprove())
                                                <flux:menu.separator />
                                                <flux:menu.item wire:click="confirmApproveWork({{ $booking->id }})" icon="check" class="text-green-600">
                                                    Approve Work
                                                </flux:menu.item>
                                            @endif
                                            @if($booking->canRequestRevision())
                                                <flux:menu.separator />
                                                <flux:menu.item wire:click="confirmRevision({{ $booking->id }})" icon="pencil-square" class="text-yellow-600">
                                                    Request Revision
                                                </flux:menu.item>
                                            @endif
                                            @if($booking->canDispute())
                                                <flux:menu.separator />
                                                <flux:menu.item wire:click="confirmDispute({{ $booking->id }})" icon="exclamation-triangle" class="text-red-600">
                                                    Open Dispute
                                                </flux:menu.item>
                                            @endif
                                        @else
                                            @if($booking->status === BookingStatus::INQUIRY)
                                                <flux:menu.separator />
                                                <flux:menu.item wire:click="confirmApprove({{ $booking->id }})" icon="check" class="text-green-600">
                                                    Approve
                                                </flux:menu.item>
                                                <flux:menu.item wire:click="confirmCounter({{ $booking->id }})" icon="banknotes" class="text-purple-600">
                                                    Counter Offer
                                                </flux:menu.item>
                                                <flux:menu.item wire:click="confirmReject({{ $booking->id }})" icon="x-mark" class="text-red-600">
                                                    Reject
                                                </flux:menu.item>
                                            @endif
                                        @endif
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
            <flux:icon.calendar-days class="mx-auto h-12 w-12 text-zinc-400" />
            <flux:heading size="lg" class="mt-4">No bookings yet</flux:heading>
            <flux:text class="mt-2 text-zinc-600 dark:text-zinc-400">
                @if($statusFilter !== 'all' || $typeFilter !== 'all')
                    No bookings found with the current filters.
                @elseif($this->isBrand)
                    When you book creators, your bookings will appear here.
                @else
                    When brands book your services, they'll appear here.
                @endif
            </flux:text>
            @if($statusFilter !== 'all' || $typeFilter !== 'all')
                <flux:button wire:click="$set('statusFilter', 'all'); $set('typeFilter', 'all')" variant="ghost" class="mt-4">
                    Clear Filters
                </flux:button>
            @endif
        </div>
    @endif

    @if($this->isBrand)
        @if($selectedBooking)
            <x-bookings.approve-work-modal :booking="$selectedBooking" />
            <x-bookings.revision-request-modal :booking="$selectedBooking" />
            <x-bookings.dispute-modal :booking="$selectedBooking" />

            <flux:modal wire:model.self="showRatingPrompt" class="md:w-2xl">
                <x-bookings.rating-prompt
                    :creator-name="$selectedBooking->creator?->name ?? $selectedBooking->product?->workspace?->name"
                    :rating-value="$ratingValue"
                    :selected-tags="$selectedTags"
                    :available-tags="$availableTags"
                />
            </flux:modal>
        @endif
    @else
        <flux:modal wire:model.self="showApproveModal" class="md:w-md">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Approve Inquiry</flux:heading>
                    <flux:text class="mt-2 text-zinc-500">The brand will be emailed a secure link to fill in requirements and complete payment.</flux:text>
                </div>

                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:button variant="ghost" @click="$wire.set('showApproveModal', false)">Cancel</flux:button>
                    <flux:button
                        variant="primary"
                        icon="check"
                        wire:click="approveInquiry"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-75"
                    >
                        <span wire:loading.remove wire:target="approveInquiry">Approve</span>
                        <span wire:loading wire:target="approveInquiry">Approving…</span>
                    </flux:button>
                </div>
            </div>
        </flux:modal>

        @if($selectedBooking)
            <x-bookings.reject-inquiry-modal :booking="$selectedBooking" />
            <x-bookings.counter-inquiry-modal :booking="$selectedBooking" />
        @endif
    @endif
</div>
</div>