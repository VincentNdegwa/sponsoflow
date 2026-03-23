<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\WorkspaceRating;
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
    public bool $showRatingPrompt = false;

    // Inquiry actions
    public bool $showRejectModal = false;
    public bool $showCounterModal = false;
    public string $rejectionNote = '';
    public string $counterNote = '';
    public string $counterAmount = '';

    // Marketplace application actions
    public bool $showMarketplaceRejectModal = false;
    public string $marketplaceRejectionNote = '';

    // Brand counter-offer response
    public bool $showCounterAcceptStep = false;
    public array $requirementData = [];
    public bool $brandIsProcessing = false;
    public ?string $brandErrorMessage = null;

    public bool $ratingSubmitted = false;
    public int $ratingValue = 0;
    public array $selectedTags = [];
    public string $ratingComment = '';

    /** @var array<string> */
    public array $availableTags = ['Professional', 'Fast Delivery', 'Great Quality', 'Creative'];

    public function mount(Booking $booking): void
    {
        $workspace = currentWorkspace();

        if ($booking->workspace_id !== $workspace?->id &&
            $booking->brand_workspace_id !== $workspace?->id &&
            $booking->brand_user_id !== Auth::id()) {
            abort(404);
        }

        $this->booking = $booking->load([
            'product.requirements',
            'product.workspace',
            'brandUser',
            'brandWorkspace',
            'slot',
            'latestSubmission',
            'submissions',
            'payments',
            'latestRating',
            'creator',
        ]);
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

    public function approveMarketplaceApplication(): void
    {
        $result = app(BookingService::class)->approveMarketplaceApplicationBooking($this->booking);

        if ($result['success']) {
            $this->booking->refresh();
            $this->dispatch('success', 'Marketplace application approved — the brand has been emailed a payment link.');
        } else {
            $this->dispatch('error', $result['error']);
        }
    }

    public function rejectMarketplaceApplication(): void
    {
        $result = app(BookingService::class)->rejectMarketplaceApplicationBooking(
            $this->booking,
            $this->marketplaceRejectionNote ?: null,
        );

        if ($result['success']) {
            $this->booking->refresh();
            $this->showMarketplaceRejectModal = false;
            $this->marketplaceRejectionNote = '';
            $this->dispatch('success', 'Marketplace application rejected — the brand has been notified.');
        } else {
            $this->dispatch('error', $result['error']);
        }
    }

    public function brandAcceptCounter(): void
    {
        $this->showCounterAcceptStep = true;
    }

    public function brandDeclineCounter(): void
    {
        $result = app(BookingService::class)->rejectInquiry($this->booking, null);

        if ($result['success']) {
            $this->booking->refresh();
            $this->dispatch('success', 'Counter-offer declined — the creator has been notified.');
        } else {
            $this->dispatch('error', $result['error']);
        }
    }

    public function brandProceedToPayment(): void
    {
        $this->processBrandInquiryPayment(true);
    }

    public function brandProceedApprovedInquiryToPayment(): void
    {
        $this->processBrandInquiryPayment(false);
    }

    private function processBrandInquiryPayment(bool $acceptingCounter): void
    {
        $product = $this->booking->product;

        $validationErrors = app(BookingService::class)->validateRequirementData($product, $this->requirementData);
        foreach ($validationErrors as $field => $message) {
            $this->addError($field, $message);
        }
        if ($validationErrors) {
            return;
        }

        $this->brandIsProcessing = true;
        $this->brandErrorMessage = null;

        try {
            $result = app(BookingService::class)->fulfillInquiryBooking(
                $this->booking,
                $this->requirementData,
                $acceptingCounter,
            );

            if ($result['success']) {
                $this->redirect($result['checkout_url'], navigate: false);
            } else {
                $this->brandErrorMessage = $result['error'];
            }
        } catch (\Exception $e) {
            $this->brandErrorMessage = 'An unexpected error occurred. Please try again.';
        } finally {
            $this->brandIsProcessing = false;
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
            $this->showApproveModal = false;

            if ($this->hasExistingRating($this->booking)) {
                $this->dispatch('success', 'Work approved! Payment has been released to the creator.');

                return;
            }

            $this->showRatingPrompt = true;
            $this->dispatch('success', 'Work approved! Payment has been released. You can now rate the creator.');
        } else {
            $this->dispatch('error', $result['error']);
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

        $result = app(BookingService::class)->submitRating(
            $this->booking,
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
        $this->resetRatingState();
        $this->dispatch('success', 'Thanks for the rating!');
    }

    public function skipRating(): void
    {
        $this->showRatingPrompt = false;
        $this->resetRatingState();
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

    <div class="grid gap-8 xl:grid-cols-[minmax(0,1.35fr)_minmax(280px,0.85fr)]">
        <div class="space-y-8">

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
                            <flux:heading size="lg">{{ $booking->formatAmount() }}</flux:heading>
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
                <div class="rounded-lg border border-amber-200 bg-accent-50 p-6 dark:border-amber-700 dark:bg-accent-950">
                    <flux:heading size="lg" class="mb-1">Awaiting Brand Response</flux:heading>
                    <flux:text class="mb-4 text-zinc-600 dark:text-zinc-400">
                        You sent a counter-offer of <strong>{{ $booking->formatAmount((float) $booking->counter_amount) }}</strong> to the brand.
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

            @if($this->isCreator() && $booking->canCreatorApproveMarketplaceApplication())
                <div class="rounded-lg border border-violet-200 bg-violet-50 p-6 dark:border-violet-700 dark:bg-violet-950">
                    <flux:heading size="lg" class="mb-1">Marketplace Match Ready</flux:heading>
                    <flux:text class="mb-4 text-zinc-600 dark:text-zinc-400">
                        This brand approved your marketplace application for <strong>{{ $booking->product?->name }}</strong>.
                        Confirm to send them a payment link or reject if it is not a fit.
                    </flux:text>

                    <div class="flex flex-wrap gap-3">
                        <flux:button
                            wire:click="approveMarketplaceApplication"
                            wire:loading.attr="disabled"
                            variant="primary"
                            icon="check"
                        >
                            <span wire:loading.remove wire:target="approveMarketplaceApplication">Approve Match</span>
                            <span wire:loading wire:target="approveMarketplaceApplication">Approving…</span>
                        </flux:button>

                        <flux:button
                            wire:click="$set('showMarketplaceRejectModal', true)"
                            variant="danger"
                            icon="x-mark"
                        >
                            Reject
                        </flux:button>
                    </div>
                </div>
            @endif

            @if($this->isBrandUser() && $booking->isMarketplaceApplication() && $booking->status === \App\Enums\BookingStatus::PENDING)
                <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-6 dark:border-indigo-700 dark:bg-indigo-950">
                    <flux:heading size="lg" class="mb-1">Awaiting Creator Confirmation</flux:heading>
                    <flux:text class="text-zinc-600 dark:text-zinc-400">
                        The creator is reviewing your approved application. Once they confirm, you'll receive a payment link.
                    </flux:text>
                </div>
            @endif

            @if($this->isBrandUser() && $booking->canAcceptCounter())
                @if(! $showCounterAcceptStep)
                    <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-6 dark:border-indigo-700 dark:bg-indigo-950">
                        <flux:heading size="lg" class="mb-1">You Have a Counter-Offer</flux:heading>
                        <flux:text class="mb-6 text-zinc-600 dark:text-zinc-400">
                            The creator has reviewed your inquiry and proposed a different rate.
                            Review the details and choose how to respond.
                        </flux:text>

                        <x-bookings.counter-offer-respond :booking="$booking" accept-action="brandAcceptCounter" decline-action="brandDeclineCounter" />
                    </div>
                @else
                    <x-bookings.inquiry-payment-step
                        :booking="$booking"
                        purpose="accept_counter"
                        action="brandProceedToPayment"
                        :error="$brandErrorMessage"
                        back-action="$set('showCounterAcceptStep', false)"
                        back-label="← Back to counter-offer"
                    />
                @endif
            @endif

            @if($this->isBrandUser() && $booking->canProceedInquiryPayment())
                <x-bookings.inquiry-payment-step
                    :booking="$booking"
                    purpose="respond"
                    action="brandProceedApprovedInquiryToPayment"
                    :error="$brandErrorMessage"
                />
            @endif

            @if($this->isCreator() && $booking->status === \App\Enums\BookingStatus::PENDING_PAYMENT && $booking->isCreatorInitiated())
                @php $inviteToken = $booking->inviteTokens()->latest()->first(); @endphp
                @if($inviteToken && $inviteToken->isValid())
                    <div class="rounded-lg border border-amber-200 bg-accent-50 p-6 dark:border-amber-700 dark:bg-accent-950">
                        <flux:heading size="lg" class="mb-1">Awaiting Brand Payment</flux:heading>
                        <flux:text class="mb-4 text-zinc-600 dark:text-zinc-400">
                            Share the link below with the brand so they can complete the payment.
                            The link expires {{ $inviteToken->expires_at->diffForHumans() }}.
                        </flux:text>

                        @php $inviteUrl = route('bookings.invite', ['token' => $inviteToken->token]); @endphp

                        <div class="flex items-center gap-2">
                            <flux:input value="{{ $inviteUrl }}" readonly class="font-mono text-sm" />
                            <flux:button
                                variant="filled"
                                icon="clipboard"
                                x-data
                                x-on:click="navigator.clipboard.writeText('{{ $inviteUrl }}').then(() => $dispatch('copied'))"
                                @copied.window="$el.textContent = 'Copied!'; setTimeout(() => $el.innerHTML = '<svg ...></svg>&nbsp;Copy', 2000)"
                            >
                                Copy
                            </flux:button>
                        </div>
                    </div>
                @endif
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

            @if($this->isBrandUser() && $booking->canReviewSubmittedWork())
                <div class="rounded-lg border border-green-200 bg-green-50 p-6 dark:border-green-700 dark:bg-green-950">
                    <flux:heading size="lg" class="mb-2">Review Submitted Work</flux:heading>
                    <flux:text class="mb-4 text-zinc-600 dark:text-zinc-400">
                        The creator has submitted the work. Please review and take action.
                        @if($booking->auto_approve_at)
                            Auto-approves in <strong>{{ $booking->auto_approve_at->diffForHumans() }}</strong> if no action is taken.
                        @endif
                    </flux:text>

                    <x-bookings.work-review-actions :booking="$booking" />
                </div>
            @endif

            @if($booking->latestSubmission)
                <x-bookings.submitted-work :submission="$booking->latestSubmission" :revision-count="$booking->revision_count" :max-revisions="$booking->max_revisions" />
            @endif

            @php $isBrandViewer = $this->isBrandUser(); @endphp

            <section class="space-y-6">
                <div class="mb-5 flex items-center justify-between gap-3">
                    <div>
                        <flux:heading size="lg">Delivery Studio</flux:heading>
                        <flux:text class="text-sm text-zinc-500">Everything related to execution, proof, and settlement</flux:text>
                    </div>
                    <flux:badge size="sm" color="zinc">{{ $booking->status->label() }}</flux:badge>
                </div>

                <div class="space-y-6">
                    <x-bookings.show.payment-history :booking="$booking" :is-brand-user="$isBrandViewer" />
                    <x-bookings.show.submission-history :booking="$booking" />
                    <x-bookings.show.product-details :booking="$booking" :is-brand-user="$isBrandViewer" />
                </div>
            </section>

            <section class="rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
                <div class="mb-4 flex items-center gap-2">
                    <div class="rounded-lg bg-zinc-100 p-2 dark:bg-zinc-900">
                        <flux:icon.information-circle class="h-5 w-5 text-accent" />
                    </div>
                    <flux:heading size="lg">{{ $this->isBrandUser() ? 'Creator Profile' : ($booking->brandUser ? 'Brand Profile' : 'Guest Profile') }}</flux:heading>
                </div>

                <dl class="space-y-4">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">Name</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                            {{ $this->isBrandUser() ? $booking->creator?->name : ($booking->brandUser?->name ?? $booking->guest_name) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">Email</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                            {{ $this->isBrandUser() ? $booking->creator?->email : ($booking->brandUser?->email ?? $booking->guest_email) }}
                        </dd>
                    </div>
                    @if($this->isBrandUser() && $booking->product?->workspace?->name)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">Workspace</dt>
                            <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $booking->product->workspace->name }}</dd>
                        </div>
                    @endif
                    @if(! $this->isBrandUser() && ($booking->guest_company || $booking->brandWorkspace?->name))
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">Company</dt>
                            <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $booking->guest_company ?? $booking->brandWorkspace?->name }}</dd>
                        </div>
                    @endif
                </dl>
            </section>

            @if($booking->notes)
                <section class="rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
                    <div class="mb-4 flex items-center gap-2">
                        <div class="rounded-lg bg-zinc-100 p-2 dark:bg-zinc-900">
                            <flux:icon.chat-bubble-left-right class="h-5 w-5 text-accent" />
                        </div>
                        <div>
                            <flux:heading size="lg">Inquiry Note</flux:heading>
                            <flux:text class="text-xs text-zinc-500">Provided by brand</flux:text>
                        </div>
                    </div>

                    <flux:text class="whitespace-pre-wrap text-sm">{{ $booking->notes }}</flux:text>
                </section>
            @endif

            <x-campaigns.payload-preview
                :campaign-details="$booking->campaign_details"
                :campaign-deliverables="$booking->campaign_deliverables"
                :requirement-data="$booking->requirement_data"
                :requirements="$booking->product?->requirements ?? collect()"
            />

        </div>

        <aside class="space-y-6 xl:sticky xl:top-6 xl:self-start">
            <section class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">

                <div>
                    <div class="mb-5 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <div class="rounded-lg bg-zinc-100 p-2 dark:bg-zinc-900">
                                <flux:icon.shield-check class="h-5 w-5 text-accent" />
                            </div>
                            <flux:heading size="lg">Booking Overview</flux:heading>
                        </div>
                        <flux:badge :color="$booking->status->badgeColor()">{{ $booking->status->label() }}</flux:badge>
                    </div>

                    <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        <div class="py-3">
                            <div class="flex items-center justify-between gap-2 text-zinc-500">
                                <div class="flex items-center gap-2">
                                    <flux:text class="text-xs font-medium uppercase tracking-wide">Type</flux:text>
                                </div>
                            </div>
                            <div class="mt-2">
                                <flux:badge color="amber">{{ $booking->type->label() }}</flux:badge>
                            </div>
                        </div>

                        <div class="py-3">
                            <div class="flex items-center gap-2 text-zinc-500">
                                <flux:text class="text-xs font-medium uppercase tracking-wide">Amount</flux:text>
                            </div>
                            <flux:heading class="mt-1">{{ $booking->formatAmount() }}</flux:heading>
                        </div>

                        @if($booking->revision_count > 0)
                            <div class="py-3">
                                <div class="flex items-center gap-2 text-zinc-500">
                                    <div class="rounded-md bg-zinc-100 p-1.5 dark:bg-zinc-900">
                                        <flux:icon.arrow-path class="h-4 w-4 text-accent" />
                                    </div>
                                    <flux:text class="text-xs font-medium uppercase tracking-wide">Revisions Used</flux:text>
                                </div>
                                <div class="mt-2 flex items-center gap-2">
                                    <flux:badge size="sm" color="zinc">{{ $booking->revision_count }} / {{ $booking->max_revisions }}</flux:badge>
                                </div>
                            </div>
                        @endif

                        @if($booking->auto_approve_at && $booking->status === \App\Enums\BookingStatus::PROCESSING)
                            <div class="py-3">
                                <div class="flex items-center gap-2 text-zinc-500">
                                    <div class="rounded-md bg-zinc-100 p-1.5 dark:bg-zinc-900">
                                        <flux:icon.clock class="h-4 w-4 text-accent" />
                                    </div>
                                    <flux:text class="text-xs font-medium uppercase tracking-wide">Auto-Approval</flux:text>
                                </div>
                                <flux:text class="mt-1 text-sm">{{ $booking->auto_approve_at->diffForHumans() }}</flux:text>
                            </div>
                        @endif
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                <div class="mb-5 flex items-center gap-2">
                    <div class="rounded-lg bg-zinc-100 p-2 dark:bg-zinc-900">
                        <flux:icon.clock class="h-5 w-5 text-accent" />
                    </div>
                    <flux:heading size="lg">Timeline</flux:heading>
                </div>

                <div class="space-y-5 border-l border-dashed border-zinc-300 pl-4 dark:border-zinc-600">
                    <div class="relative">
                        <span class="absolute -left-[1.33rem] top-1 h-2.5 w-2.5 rounded-full bg-sky-500"></span>
                        <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">Created</flux:text>
                        <flux:text class="mt-1 text-sm">{{ formatWorkspaceDate($booking->created_at) }} at {{ formatWorkspaceTime($booking->created_at) }}</flux:text>
                    </div>

                    @if($booking->updated_at->ne($booking->created_at))
                        <div class="relative">
                            <span class="absolute -left-[1.33rem] top-1 h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                            <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">Last Updated</flux:text>
                            <flux:text class="mt-1 text-sm">{{ formatWorkspaceDate($booking->updated_at) }} at {{ formatWorkspaceTime($booking->updated_at) }}</flux:text>
                        </div>
                    @endif
                </div>
            </section>

            <x-bookings.show.rating-summary :booking="$booking" :is-brand-user="$isBrandViewer" />
        </aside>
    </div>

    <x-bookings.submit-work-modal :booking="$booking" />
    <x-bookings.approve-work-modal :booking="$booking" />
    <x-bookings.revision-request-modal :booking="$booking" />
    <x-bookings.dispute-modal :booking="$booking" />
    <x-bookings.reject-inquiry-modal :booking="$booking" />
    <x-bookings.reject-marketplace-modal :booking="$booking" />
    <x-bookings.counter-inquiry-modal :booking="$booking" />

    <flux:modal wire:model.self="showRatingPrompt" class="md:w-2xl">
        <x-bookings.rating-prompt
            :creator-name="$booking->product?->workspace?->name ?? $booking->creator?->name"
            :rating-value="$ratingValue"
            :selected-tags="$selectedTags"
            :available-tags="$availableTags"
        />
    </flux:modal>
</div>