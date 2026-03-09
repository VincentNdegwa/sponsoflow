<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::app'), Title('Booking Details')] class extends Component {
    use WithFileUploads;

    public Booking $booking;

    #[Validate('nullable|url')]
    public string $workUrl = '';

    #[Validate('nullable|image|max:5120')]
    public $screenshot = null;

    public string $revisionNotes = '';
    public string $disputeReason = '';
    public bool $showSubmitForm = false;
    public bool $showRevisionForm = false;
    public bool $showDisputeForm = false;
    public bool $showApproveModal = false;

    // Inquiry actions
    public bool $showRejectModal = false;
    public bool $showCounterModal = false;
    public string $rejectionNote = '';
    public string $counterNote = '';
    public string $counterAmount = '';

    public function mount(Booking $booking): void
    {
        $workspace = currentWorkspace();

        if ($booking->workspace_id !== $workspace?->id &&
            $booking->brand_workspace_id !== $workspace?->id &&
            $booking->brand_user_id !== Auth::id()) {
            abort(404);
        }

        $this->booking = $booking->load(['product.requirements', 'product.workspace', 'brandUser', 'brandWorkspace', 'slot', 'latestSubmission', 'creator']);
    }

    public function approveInquiry(): void
    {
        $result = app(BookingService::class)->approveInquiry($this->booking);

        if ($result['success']) {
            $this->booking->refresh();
            $this->dispatch('success', 'Inquiry approved — the brand has been emailed a link to complete payment.');
        } else {
            $this->dispatch('error', $result['error']);
        }
    }

    public function rejectInquiry(): void
    {
        $result = app(BookingService::class)->rejectInquiry($this->booking, $this->rejectionNote ?: null);

        if ($result['success']) {
            $this->booking->refresh();
            $this->showRejectModal = false;
            $this->rejectionNote = '';
            $this->dispatch('success', 'Inquiry rejected — the brand has been notified.');
        } else {
            $this->dispatch('error', $result['error']);
        }
    }

    public function counterInquiry(): void
    {
        $this->validate([
            'counterAmount' => 'required|numeric|min:1',
        ]);

        $result = app(BookingService::class)->counterInquiry(
            $this->booking,
            (float) $this->counterAmount,
            $this->counterNote ?: null,
        );

        if ($result['success']) {
            $this->booking->refresh();
            $this->showCounterModal = false;
            $this->counterAmount = '';
            $this->counterNote = '';
            $this->dispatch('success', 'Counter-offer sent — the brand has been notified.');
        } else {
            $this->dispatch('error', $result['error']);
        }
    }

    public function submitWork(): void
    {
        $this->validateOnly('workUrl');
        $this->validateOnly('screenshot');

        if (! $this->workUrl && ! $this->screenshot) {
            $this->addError('workUrl', 'Please provide a link or a screenshot (or both).');
            return;
        }

        $screenshotPath = null;
        if ($this->screenshot) {
            $screenshotPath = $this->screenshot->store('booking-screenshots', 'public');
        }

        $result = app(BookingService::class)->submitWork($this->booking, $this->workUrl ?: null, $screenshotPath);

        if ($result['success']) {
            $this->booking->refresh()->load(['latestSubmission']);
            $this->showSubmitForm = false;
            $this->workUrl = '';
            $this->screenshot = null;
            $this->dispatch('success', 'Work submitted! The brand has been notified and has 72 hours to review.');
        } else {
            $this->addError('workUrl', $result['error']);
        }
    }

    public function approveWork(): void
    {
        $result = app(BookingService::class)->approveWork($this->booking);

        if ($result['success']) {
            $this->booking->refresh();
            $this->dispatch('success', 'Work approved! Payment has been released to the creator.');
        } else {
            $this->dispatch('error', $result['error']);
        }
    }

    public function requestRevision(): void
    {
        $this->validate(['revisionNotes' => 'required|string|min:10']);

        $result = app(BookingService::class)->requestRevision($this->booking, $this->revisionNotes);

        if ($result['success']) {
            $this->booking->refresh()->load(['latestSubmission']);
            $this->showRevisionForm = false;
            $this->revisionNotes = '';
            $this->dispatch('success', 'Revision request sent to the creator.');
        } else {
            $this->dispatch('error', $result['error']);
        }
    }

    public function openDispute(): void
    {
        $this->validate(['disputeReason' => 'required|string|min:10']);

        $result = app(BookingService::class)->openDispute($this->booking, $this->disputeReason);

        if ($result['success']) {
            $this->booking->refresh();
            $this->showDisputeForm = false;
            $this->disputeReason = '';
            $this->dispatch('success', 'Dispute opened. Our team will review within 48 hours.');
        } else {
            $this->dispatch('error', $result['error']);
        }
    }

    public function isCreator(): bool
    {
        return Auth::id() === $this->booking->creator_id;
    }

    public function isBrandUser(): bool
    {
        $workspace = currentWorkspace();

        return Auth::id() === $this->booking->brand_user_id
            || ($workspace && $workspace->id === $this->booking->brand_workspace_id);
    }
}; ?>

<div>
    <div class="mb-8">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item href="{{ route('bookings.index') }}">Bookings</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $booking->product->name }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>
        
        <div class="mt-4 flex items-center justify-between">
            <div>
                <flux:heading size="xl">Booking Details</flux:heading>
                <flux:subheading>{{ $booking->product->name }}</flux:subheading>
            </div>
            
            <flux:button href="{{ route('bookings.index') }}" variant="ghost" icon="arrow-left">
                Back to Bookings
            </flux:button>
        </div>
    </div>

    <div class="grid gap-8 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-8">

            @if($this->isCreator() && $booking->canApproveInquiry())
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-6 dark:border-blue-700 dark:bg-blue-950">
                    <flux:heading size="lg" class="mb-1">New Inquiry</flux:heading>
                    <flux:text class="mb-4 text-zinc-600 dark:text-zinc-400">
                        You have a new inquiry from <strong>{{ $booking->guest_name ?? $booking->brandUser?->name }}</strong>.
                        Review the details and choose how to respond below.
                    </flux:text>

                    @if($booking->notes)
                        <blockquote class="mb-4 border-l-4 border-blue-300 pl-4 italic text-zinc-600 dark:border-blue-600 dark:text-zinc-400">
                            "{{ $booking->notes }}"
                        </blockquote>
                    @endif

                    <div class="mb-4 flex items-center gap-6">
                        <div>
                            <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">Offered Budget</flux:text>
                            <flux:heading size="lg">{{ formatMoney($booking->amount_paid) }}</flux:heading>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <flux:button
                            wire:click="approveInquiry"
                            wire:loading.attr="disabled"
                            variant="primary"
                            icon="check"
                        >
                            <span wire:loading.remove wire:target="approveInquiry">Approve</span>
                            <span wire:loading wire:target="approveInquiry">Approving…</span>
                        </flux:button>

                        <flux:button
                            wire:click="$set('showCounterModal', true)"
                            variant="filled"
                            icon="arrow-path"
                        >
                            Counter Offer
                        </flux:button>

                        <flux:button
                            wire:click="$set('showRejectModal', true)"
                            variant="danger"
                            icon="x-mark"
                        >
                            Reject
                        </flux:button>
                    </div>
                </div>
            @endif

            @if($this->isCreator() && $booking->canAcceptCounter())
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-6 dark:border-amber-700 dark:bg-amber-950">
                    <flux:heading size="lg" class="mb-1">Awaiting Brand Response</flux:heading>
                    <flux:text class="mb-4 text-zinc-600 dark:text-zinc-400">
                        You sent a counter-offer of <strong>{{ formatMoney($booking->counter_amount) }}</strong> to the brand.
                        Waiting for them to accept or decline.
                    </flux:text>

                    @if($booking->creator_notes)
                        <blockquote class="mb-4 border-l-4 border-amber-300 pl-4 italic text-zinc-600 dark:border-amber-600 dark:text-zinc-400">
                            "{{ $booking->creator_notes }}"
                        </blockquote>
                    @endif

                    <flux:button
                        wire:click="$set('showRejectModal', true)"
                        variant="danger"
                        icon="x-mark"
                    >
                        Withdraw &amp; Reject
                    </flux:button>
                </div>
            @endif

            @if($this->isCreator() && $booking->canSubmitWork())
                <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-6 dark:border-indigo-700 dark:bg-indigo-950">
                    <flux:heading size="lg" class="mb-2">
                        {{ $booking->status === \App\Enums\BookingStatus::REVISION_REQUESTED ? 'Re-submit Revised Work' : 'Submit Your Work' }}
                    </flux:heading>
                    <flux:text class="mb-4 text-zinc-600 dark:text-zinc-400">
                        {{ $booking->status === \App\Enums\BookingStatus::REVISION_REQUESTED ? 'The brand has requested a revision. Upload the updated version below.' : 'Upload the completed work for the brand to review.' }}
                    </flux:text>

                    <flux:button
                        wire:click="$set('showSubmitForm', true)"
                        variant="primary"
                        icon="arrow-up-tray"
                    >
                        Open Submission Form
                    </flux:button>
                </div>
            @endif

            @if($this->isBrandUser() && $booking->canApprove())
                <div class="rounded-lg border border-green-200 bg-green-50 p-6 dark:border-green-700 dark:bg-green-950">
                    <flux:heading size="lg" class="mb-2">Review Submitted Work</flux:heading>
                    <flux:text class="mb-4 text-zinc-600 dark:text-zinc-400">
                        The creator has submitted the work. Please review and take action.
                        @if($booking->auto_approve_at)
                            Auto-approves in <strong>{{ $booking->auto_approve_at->diffForHumans() }}</strong> if no action is taken.
                        @endif
                    </flux:text>

                    <div class="flex flex-wrap gap-3">
                        <flux:button
                            wire:click="$set('showApproveModal', true)"
                            variant="primary"
                            icon="check"
                        >
                            Approve Work
                        </flux:button>

                        @if($booking->canRequestRevision())
                            <flux:button
                                wire:click="$set('showRevisionForm', true)"
                                variant="filled"
                                icon="arrow-path"
                            >
                                Request Revision
                                <flux:badge size="sm" class="ml-1">{{ $booking->max_revisions - $booking->revision_count }} left</flux:badge>
                            </flux:button>
                        @endif

                        @if($booking->canDispute())
                            <flux:button
                                wire:click="$set('showDisputeForm', true)"
                                variant="danger"
                                icon="shield-exclamation"
                            >
                                Open Dispute
                            </flux:button>
                        @endif
                    </div>
                </div>
            @endif

            @if($booking->latestSubmission)
                <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
                    <flux:heading size="lg" class="mb-4">Submitted Work</flux:heading>
                    <div class="space-y-4">
                        @if($booking->latestSubmission->work_url)
                            <div>
                                <flux:text class="text-sm font-medium text-zinc-500">Content Link</flux:text>
                                <a href="{{ $booking->latestSubmission->work_url }}" target="_blank" rel="noopener noreferrer"
                                   class="mt-1 flex items-center gap-1 text-indigo-600 underline dark:text-indigo-400">
                                    {{ $booking->latestSubmission->work_url }}
                                    <flux:icon.arrow-top-right-on-square class="h-4 w-4" />
                                </a>
                            </div>
                        @endif
                        @if($booking->latestSubmission->screenshot_path)
                            <div>
                                <flux:text class="text-sm font-medium text-zinc-500 mb-2">Screenshot</flux:text>
                                <img src="{{ Storage::url($booking->latestSubmission->screenshot_path) }}"
                                     alt="Work screenshot"
                                     class="rounded-lg max-w-full border border-zinc-200 dark:border-zinc-600" />
                            </div>
                        @endif
                        <flux:text class="text-xs text-zinc-400">
                            Submitted {{ $booking->latestSubmission->created_at->diffForHumans() }}
                            @if($booking->revision_count > 0) &mdash; Revision {{ $booking->revision_count }} of {{ $booking->max_revisions }} @endif
                        </flux:text>
                    </div>
                </div>
            @endif

            <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
                @if($this->isBrandUser())
                    <flux:heading size="lg" class="mb-4">Creator Information</flux:heading>

                    <div class="space-y-4">
                        <div class="grid gap-6 sm:grid-cols-2">
                            <div>
                                <flux:text class="text-sm font-medium text-zinc-500">Name</flux:text>
                                <flux:text class="mt-1">{{ $booking->creator?->name }}</flux:text>
                            </div>

                            <div>
                                <flux:text class="text-sm font-medium text-zinc-500">Email</flux:text>
                                <flux:text class="mt-1">{{ $booking->creator?->email }}</flux:text>
                            </div>

                            @if($booking->product?->workspace?->name)
                                <div>
                                    <flux:text class="text-sm font-medium text-zinc-500">Workspace</flux:text>
                                    <flux:text class="mt-1">{{ $booking->product->workspace->name }}</flux:text>
                                </div>
                            @endif
                        </div>
                    </div>
                @else
                    <flux:heading size="lg" class="mb-4">{{ $booking->brandUser ? 'Brand' : 'Guest' }} Information</flux:heading>

                    <div class="space-y-4">
                        <div class="grid gap-6 sm:grid-cols-2">
                            <div>
                                <flux:text class="text-sm font-medium text-zinc-500">Name</flux:text>
                                <flux:text class="mt-1">{{ $booking->brandUser?->name ?? $booking->guest_name }}</flux:text>
                            </div>

                            <div>
                                <flux:text class="text-sm font-medium text-zinc-500">Email</flux:text>
                                <flux:text class="mt-1">{{ $booking->brandUser?->email ?? $booking->guest_email }}</flux:text>
                            </div>

                            @if($booking->guest_company || $booking->brandWorkspace?->name)
                                <div>
                                    <flux:text class="text-sm font-medium text-zinc-500">Company</flux:text>
                                    <flux:text class="mt-1">{{ $booking->guest_company ?? $booking->brandWorkspace?->name }}</flux:text>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            @if($booking->requirement_data)
                <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
                    <flux:heading size="lg" class="mb-4">Campaign Details</flux:heading>
                    
                    <div class="space-y-4">
                        @foreach($booking->requirement_data as $key => $value)
                            @if($value)
                                <div>
                                    <flux:text class="text-sm font-medium text-zinc-500">{{ ucfirst(str_replace('_', ' ', $key)) }}</flux:text>
                                    <flux:text class="mt-1">{{ is_array($value) ? implode(', ', $value) : $value }}</flux:text>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

            @if($booking->notes)
                <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
                    <flux:heading size="lg" class="mb-4">Notes</flux:heading>
                    <flux:text class="whitespace-pre-wrap">{{ $booking->notes }}</flux:text>
                </div>
            @endif
        </div>

        <div class="space-y-6">
            <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
                <flux:heading size="lg" class="mb-4">Booking Status</flux:heading>
                
                <div class="space-y-4">
                    <div>
                        <flux:text class="text-sm font-medium text-zinc-500">Status</flux:text>
                        <div class="mt-1">
                            <flux:badge :color="$booking->status->badgeColor()">
                                {{ $booking->status->label() }}
                            </flux:badge>
                        </div>
                    </div>
                    
                    <div>
                        <flux:text class="text-sm font-medium text-zinc-500">Type</flux:text>
                        <div class="mt-1">
                            <flux:badge :color="$booking->type->badgeColor()">
                                {{ $booking->type->label() }}
                            </flux:badge>
                        </div>
                    </div>
                    
                    <div>
                        <flux:text class="text-sm font-medium text-zinc-500">Amount</flux:text>
                        <flux:heading class="mt-1">{{ formatMoney($booking->amount_paid) }}</flux:heading>
                    </div>

                    @if($booking->revision_count > 0)
                        <div>
                            <flux:text class="text-sm font-medium text-zinc-500">Revisions Used</flux:text>
                            <flux:text class="mt-1">{{ $booking->revision_count }} / {{ $booking->max_revisions }}</flux:text>
                        </div>
                    @endif

                    @if($booking->auto_approve_at && $booking->status === \App\Enums\BookingStatus::PROCESSING)
                        <div>
                            <flux:text class="text-sm font-medium text-zinc-500">Auto-Approves</flux:text>
                            <flux:text class="mt-1">{{ $booking->auto_approve_at->diffForHumans() }}</flux:text>
                        </div>
                    @endif
                </div>
            </div>

            @if($booking->slot)
                <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
                    <flux:heading size="lg" class="mb-4">Scheduled Time</flux:heading>
                    
                    <div class="space-y-2">
                        <div>
                            <flux:text class="text-sm font-medium text-zinc-500">Date & Time</flux:text>
                            <flux:text class="mt-1">{{ formatWorkspaceDate($booking->slot->slot_date) }}</flux:text>
                            <flux:text class="text-sm text-zinc-500">{{ formatWorkspaceTime($booking->slot->slot_date) }}</flux:text>
                        </div>
                    </div>
                </div>
            @endif

            <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
                <flux:heading size="lg" class="mb-4">Timeline</flux:heading>
                
                <div class="space-y-3">
                    <div>
                        <flux:text class="text-sm font-medium text-zinc-500">Created</flux:text>
                        <flux:text class="mt-1">{{ formatWorkspaceDate($booking->created_at) }} at {{ formatWorkspaceTime($booking->created_at) }}</flux:text>
                    </div>
                    
                    @if($booking->updated_at->ne($booking->created_at))
                        <div>
                            <flux:text class="text-sm font-medium text-zinc-500">Last Updated</flux:text>
                            <flux:text class="mt-1">{{ formatWorkspaceDate($booking->updated_at) }} at {{ formatWorkspaceTime($booking->updated_at) }}</flux:text>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <x-bookings.submit-work-modal :booking="$booking" />
    <x-bookings.approve-work-modal :booking="$booking" />
    <x-bookings.revision-request-modal :booking="$booking" />
    <x-bookings.dispute-modal :booking="$booking" />
    <x-bookings.reject-inquiry-modal :booking="$booking" />
    <x-bookings.counter-inquiry-modal :booking="$booking" />
</div>