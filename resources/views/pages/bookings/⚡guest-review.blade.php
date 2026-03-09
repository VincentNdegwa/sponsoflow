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

    public function mount(string $token): void
    {
        $this->reviewToken = BookingReviewToken::where('token', $token)
            ->with(['booking.product', 'booking.latestSubmission'])
            ->first();

        if (! $this->reviewToken || ! $this->reviewToken->isValid()) {
            $this->tokenInvalid = true;
        }
    }

    public function approveWork(): void
    {
        if (! $this->reviewToken || ! $this->reviewToken->isValid()) {
            return;
        }

        $service = app(BookingService::class);
        $result = $service->approveWork($this->reviewToken->booking);
            Log::info("Results", [
                'data' => $result
            ]);
        if ($result['success']) {
            $this->reviewToken->markUsed();
            $this->showApproveModal = false;
            $this->actionTaken = true;
            $this->actionMessage = 'approved';
        }else{
          
        }
    }

    public function requestRevision(): void
    {
        $this->validate(['revisionNotes' => 'required|string|min:10']);

        if (! $this->reviewToken || ! $this->reviewToken->isValid()) {
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

        if (! $this->reviewToken || ! $this->reviewToken->isValid()) {
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
            <div class="rounded-lg border border-red-200 bg-white p-8 text-center dark:border-red-800 dark:bg-zinc-800">
                <flux:icon.exclamation-circle class="mx-auto h-12 w-12 text-red-500" />
                <flux:heading size="xl" class="mt-4">Link Expired or Invalid</flux:heading>
                <flux:text class="mt-2 text-zinc-600 dark:text-zinc-400">
                    This review link has expired or has already been used. Please contact the creator for a new link.
                </flux:text>
            </div>

            {{-- Action already taken --}}
        @elseif($actionTaken)
            <div
                class="rounded-lg border border-green-200 bg-white p-8 text-center dark:border-green-800 dark:bg-zinc-800">
                @if ($actionMessage === 'approved')
                    <flux:icon.check-circle class="mx-auto h-12 w-12 text-green-500" />
                    <flux:heading size="xl" class="mt-4">Work Approved!</flux:heading>
                    <flux:text class="mt-2 text-zinc-600 dark:text-zinc-400">
                        The creator has been notified and payment has been released. Thank you!
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
                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-300">
                        Want to manage all your bookings in one place?
                    </flux:text>
                    <div class="mt-3">
                        <flux:button href="{{ route('login') }}" variant="primary">
                            Create or Sign In to Your Account
                        </flux:button>
                    </div>
                </div>
            </div>

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
                <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
                    <flux:heading size="lg" class="mb-4">Submitted Work</flux:heading>

                    @if ($submission?->work_url)
                        <div class="mb-4">
                            <flux:text class="text-sm font-medium text-zinc-500">Content Link</flux:text>
                            <a href="{{ $submission->work_url }}" target="_blank" rel="noopener noreferrer"
                                class="mt-1 flex items-center gap-1 text-indigo-600 underline dark:text-indigo-400">
                                {{ $submission->work_url }}
                                <flux:icon.arrow-top-right-on-square class="h-4 w-4" />
                            </a>
                        </div>
                    @endif

                    @if ($submission?->screenshot_path)
                        <div>
                            <flux:text class="mb-2 text-sm font-medium text-zinc-500">Screenshot</flux:text>
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($submission->screenshot_path) }}"
                                alt="Work screenshot"
                                class="max-w-full rounded-lg border border-zinc-200 dark:border-zinc-600" />
                        </div>
                    @endif

                    @if (!$submission?->work_url && !$submission?->screenshot_path)
                        <flux:text class="text-zinc-500">No content has been attached to this submission.</flux:text>
                    @endif
                </div>

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
                <div class="flex flex-col gap-3 sm:flex-row">
                    <flux:button wire:click="$set('showApproveModal', true)" variant="primary" icon="check"
                        class="flex-1">
                        Approve Work
                    </flux:button>

                    @if ($booking->canRequestRevision())
                        <flux:button wire:click="$set('showRevisionForm', true)" variant="filled" icon="arrow-path"
                            class="flex-1">
                            Request Revision
                        </flux:button>
                    @endif

                    @if ($booking->canDispute())
                        <flux:button wire:click="$set('showDisputeForm', true)" variant="danger"
                            icon="shield-exclamation" class="flex-1">
                            Open Dispute
                        </flux:button>
                    @endif
                </div>
            </div>

            <x-bookings.approve-work-modal :booking="$booking" />
            <x-bookings.revision-request-modal :booking="$booking" />
            <x-bookings.dispute-modal :booking="$booking" />
        @endif

    </div>
</div>
