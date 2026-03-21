<?php

use App\Enums\BookingStatus;
use App\Models\BookingReviewToken;
use App\Services\BookingService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::guest'), Title('Review Submitted Work')] class extends Component {
    public ?BookingReviewToken $reviewToken = null;
    public bool $tokenInvalid = false;
    public bool $actionTaken = false;
    public string $actionMessage = '';

    public string $revisionNotes = '';
    public string $disputeReason = '';
    public bool $showApproveModal = false;
    public bool $showRevisionForm = false;
    public bool $showDisputeForm = false;

    public bool $accessRestricted = false;
    public ?string $claimUrl = null;

    // Rating state
    public bool $ratingSubmitted = false;
    public int $ratingValue = 0;
    public array $selectedTags = [];
    public string $ratingComment = '';

    /** @var array<string> */
    public array $availableTags = ['Professional', 'Fast Delivery', 'Great Quality', 'Creative'];

    public function mount(string $token): void
    {
        $this->reviewToken = BookingReviewToken::where('token', $token)
            ->with(['booking.product.workspace', 'booking.latestSubmission'])
            ->first();

        if (! $this->reviewToken || ! $this->reviewToken->isValid()) {
            $this->tokenInvalid = true;

            return;
        }

        if (auth()->check()) {
            $workspace = auth()->user()->currentWorkspace();
            if (! $workspace || ! $workspace->isBrand()) {
                $this->accessRestricted = true;
            }
        } else {
            $brandUser = $this->reviewToken->booking->brandUser;
            if ($brandUser) {
                $resetToken = app('auth.password.broker')->createToken($brandUser);
                $this->claimUrl = url(config('app.url').route('password.reset', [
                    'token' => $resetToken,
                    'email' => $brandUser->getEmailForPasswordReset(),
                ], false));
            }
        }
    }

    public function approveWork(): void
    {
        if ($this->accessRestricted || ! $this->reviewToken || ! $this->reviewToken->isValid()) {
            return;
        }

        $service = app(BookingService::class);
        $result = $service->approveWork($this->reviewToken->booking);

        if ($result['success']) {
            $this->reviewToken->markUsed();
            $this->showApproveModal = false;
            $this->actionTaken = true;
            $this->actionMessage = 'approved';
        }
    }

    public function setRating(int $value): void
    {
        $this->ratingValue = $value;
    }

    public function toggleTag(string $tag): void
    {
        if (in_array($tag, $this->selectedTags)) {
            $this->selectedTags = array_values(array_filter($this->selectedTags, fn ($t) => $t !== $tag));
        } else {
            $this->selectedTags[] = $tag;
        }
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

        $service = app(BookingService::class);
        $service->submitRating(
            $this->reviewToken->booking,
            $this->ratingValue,
            $this->selectedTags,
            $this->ratingComment ?: null,
            $this->reviewToken->email,
        );

        $this->ratingSubmitted = true;
    }

    public function skipRating(): void
    {
        $this->ratingSubmitted = true;
    }

    public function requestRevision(): void
    {
        $this->validate(['revisionNotes' => 'required|string|min:10']);

        if ($this->accessRestricted || ! $this->reviewToken || ! $this->reviewToken->isValid()) {
            return;
        }

        $service = app(BookingService::class);
        $result = $service->requestRevision($this->reviewToken->booking, $this->revisionNotes);

        if ($result['success']) {
            $this->reviewToken->markUsed();
            $this->showRevisionForm = false;
            $this->actionTaken = true;
            $this->actionMessage = 'revision_requested';
        }
    }

    public function openDispute(): void
    {
        $this->validate(['disputeReason' => 'required|string|min:10']);

        if ($this->accessRestricted || ! $this->reviewToken || ! $this->reviewToken->isValid()) {
            return;
        }

        $service = app(BookingService::class);
        $result = $service->openDispute($this->reviewToken->booking, $this->disputeReason);

        if ($result['success']) {
            $this->reviewToken->markUsed();
            $this->showDisputeForm = false;
            $this->actionTaken = true;
            $this->actionMessage = 'dispute_opened';
        }
    }
}; ?>

<div class="flex min-h-screen items-center justify-center bg-zinc-50 p-4 dark:bg-zinc-900">
    <div class="w-full max-w-2xl">

        {{-- Token invalid --}}
        @if ($tokenInvalid)
            <x-bookings.token-invalid message="This review link has expired or has already been used. Please contact the creator for a new link." />

            {{-- Action already taken --}}
        @elseif($actionTaken)
            @if ($actionMessage === 'approved' && !$ratingSubmitted)
                {{-- Approval success + Rating prompt --}}
                <div class="space-y-6">
                    <div
                        class="rounded-lg border border-green-200 bg-white p-8 text-center dark:border-green-800 dark:bg-zinc-800">
                        <flux:icon.check-circle class="mx-auto h-14 w-14 text-green-500" />
                        <flux:heading size="xl" class="mt-4">Work Approved!</flux:heading>
                        <flux:text class="mt-2 text-zinc-600 dark:text-zinc-400">
                            Funds are being released to the creator.
                        </flux:text>
                    </div>

                    <div
                        class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
                        <x-bookings.rating-prompt
                            :creator-name="$reviewToken->booking->product->workspace->name ?? null"
                            :rating-value="$ratingValue"
                            :selected-tags="$selectedTags"
                            :available-tags="$availableTags"
                        />
                    </div>
                </div>
            @else
                <div
                    class="rounded-lg border {{ $actionMessage === 'approved' ? 'border-green-200 dark:border-green-800' : 'border-zinc-200 dark:border-zinc-700' }} bg-white p-8 text-center dark:bg-zinc-800">
                    @if ($actionMessage === 'approved')
                        <flux:icon.check-circle class="mx-auto h-12 w-12 text-green-500" />
                        <flux:heading size="xl" class="mt-4">Work Approved!</flux:heading>
                        <flux:text class="mt-2 text-zinc-600 dark:text-zinc-400">
                            The creator has been notified and payment has been released. Thank you for your feedback!
                        </flux:text>
                    @elseif($actionMessage === 'revision_requested')
                        <flux:icon.arrow-path class="mx-auto h-12 w-12 text-orange-500" />
                        <flux:heading size="xl" class="mt-4">Revision Requested</flux:heading>
                        <flux:text class="mt-2 text-zinc-600 dark:text-zinc-400">
                            Your revision notes have been sent to the creator. You'll receive a new link when the updated
                            work is ready.
                        </flux:text>
                    @else
                        <flux:icon.shield-exclamation class="mx-auto h-12 w-12 text-red-500" />
                        <flux:heading size="xl" class="mt-4">Dispute Opened</flux:heading>
                        <flux:text class="mt-2 text-zinc-600 dark:text-zinc-400">
                            Our team will review the dispute and reach out within 48 hours. Payment remains in escrow.
                        </flux:text>
                    @endif

                    <div class="mt-6 rounded-lg bg-zinc-100 p-4 dark:bg-zinc-700">
                        @guest
                            @if ($claimUrl)
                                <flux:text class="text-sm text-zinc-600 dark:text-zinc-300">
                                    Claim your SponsorFlow account to manage all your bookings in one place.
                                </flux:text>
                                <div class="mt-3">
                                    <flux:button href="{{ $claimUrl }}" variant="primary">
                                        Claim Your Account
                                    </flux:button>
                                </div>
                            @else
                                <flux:text class="text-sm text-zinc-600 dark:text-zinc-300">
                                    Want to manage all your bookings in one place?
                                </flux:text>
                                <div class="mt-3">
                                    <flux:button href="{{ route('login') }}" variant="primary">
                                        Sign In to Your Account
                                    </flux:button>
                                </div>
                            @endif
                        @endguest
                    </div>
                </div>
            @endif

            {{-- Main review view --}}
        @else
            @php
                $booking = $reviewToken->booking;
                $submission = $booking->latestSubmission;
            @endphp

            <div class="space-y-6">
                <div class="text-center">
                    <flux:heading size="xl">Review Submitted Work</flux:heading>
                    <flux:text class="mt-1 text-zinc-600 dark:text-zinc-400">{{ $booking->product->name }}</flux:text>
                </div>

                {{-- Submitted work card --}}
                <x-bookings.submitted-work :submission="$submission" />

                @if ($booking->revision_count > 0)
                    <flux:callout variant="warning" icon="information-circle">
                        <flux:callout.text>
                            This is revision {{ $booking->revision_count }} of {{ $booking->max_revisions }} allowed.
                            @if ($booking->revisionsExhausted())
                                You have used all your revisions — you may approve or open a dispute.
                            @endif
                        </flux:callout.text>
                    </flux:callout>
                @endif

                {{-- Action buttons --}}
                @if ($accessRestricted)
                    <flux:callout variant="danger" icon="shield-exclamation">
                        <flux:callout.text>
                            You don't have permission to review this submission. Only the brand that placed this booking can approve or request changes.
                        </flux:callout.text>
                    </flux:callout>
                @else
                    <x-bookings.work-review-actions :booking="$booking" />
                @endif
            </div>

            <x-bookings.approve-work-modal :booking="$booking" />
            <x-bookings.revision-request-modal :booking="$booking" />
            <x-bookings.dispute-modal :booking="$booking" />
        @endif

    </div>
</div>
