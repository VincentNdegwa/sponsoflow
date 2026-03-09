<?php

use App\Models\BookingInquiryToken;
use App\Services\BookingService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::guest'), Title('Respond to Inquiry')] class extends Component {
    public ?BookingInquiryToken $inquiryToken = null;
    public bool $tokenInvalid = false;

    /** 'overview' (for counter-offers) | 'requirements' | 'declined' */
    public string $step = 'overview';

    public bool $isProcessing = false;
    public ?string $errorMessage = null;

    public array $requirementData = [];

    public function mount(string $token): void
    {
        $this->inquiryToken = BookingInquiryToken::where('token', $token)
            ->with(['booking.product.requirements', 'booking.product.workspace'])
            ->first();

        if (! $this->inquiryToken || ! $this->inquiryToken->isValid()) {
            $this->tokenInvalid = true;

            return;
        }

        // Approved inquiries go straight to requirements; counter-offers show the overview first.
        if ($this->inquiryToken->purpose === 'respond') {
            $this->step = 'requirements';
        }
    }

    public function acceptCounter(): void
    {
        $this->step = 'requirements';
    }

    public function declineCounter(): void
    {
        if (! $this->inquiryToken || ! $this->inquiryToken->isValid()) {
            return;
        }

        $result = app(BookingService::class)->rejectInquiry($this->inquiryToken->booking, null);

        if ($result['success']) {
            $this->inquiryToken->markUsed();
            $this->step = 'declined';
        } else {
            $this->errorMessage = $result['error'];
        }
    }

    public function proceedToPayment(): void
    {
        if (! $this->inquiryToken || ! $this->inquiryToken->isValid()) {
            return;
        }

        $booking = $this->inquiryToken->booking;
        $product = $booking->product;

        foreach ($product->requirements->where('is_required', true) as $requirement) {
            if (empty($this->requirementData[$requirement->id])) {
                $this->addError("requirementData.{$requirement->id}", 'This field is required.');

                return;
            }
        }

        $this->isProcessing = true;
        $this->errorMessage = null;

        $acceptingCounter = $this->inquiryToken->purpose === 'accept_counter';

        try {
            $result = app(BookingService::class)->fulfillInquiryBooking(
                $booking,
                $this->requirementData,
                $acceptingCounter,
            );

            if ($result['success']) {
                $this->inquiryToken->markUsed();
                $this->redirect($result['checkout_url'], navigate: false);
            } else {
                $this->errorMessage = $result['error'];
            }
        } catch (\Exception $e) {
            $this->errorMessage = 'An unexpected error occurred. Please try again.';
        } finally {
            $this->isProcessing = false;
        }
    }
}; ?>

<div class="min-h-screen bg-zinc-50 dark:bg-zinc-900">
    <div class="mx-auto max-w-2xl px-4 py-12 sm:px-6">

        {{-- Invalid / expired token --}}
        @if($tokenInvalid)
            <div class="rounded-xl border border-zinc-200 bg-white p-8 text-center shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-red-100 dark:bg-red-950">
                    <flux:icon.x-circle class="h-7 w-7 text-red-600 dark:text-red-400" />
                </div>
                <flux:heading size="xl" class="mb-2">Link Expired or Invalid</flux:heading>
                <flux:text class="text-zinc-500">
                    This link has already been used or has expired. Please contact the creator if you need a new one.
                </flux:text>
            </div>
        @elseif($step === 'declined')
            {{-- Declined confirmation --}}
            <div class="rounded-xl border border-zinc-200 bg-white p-8 text-center shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-700">
                    <flux:icon.check-circle class="h-7 w-7 text-zinc-500" />
                </div>
                <flux:heading size="xl" class="mb-2">Counter-Offer Declined</flux:heading>
                <flux:text class="text-zinc-500">
                    You have declined the counter-offer. The creator has been notified and the inquiry is now closed.
                </flux:text>
            </div>
        @elseif($inquiryToken)
            @php $booking = $inquiryToken->booking; $product = $booking->product; @endphp

            {{-- Branding header --}}
            <div class="mb-8 text-center">
                <flux:heading size="xl">
                    {{ $product->workspace->name }}
                </flux:heading>
                <flux:text class="mt-1 text-zinc-500">{{ $product->name }}</flux:text>
            </div>

            {{-- Counter-offer overview --}}
            @if($step === 'overview')
                <div class="rounded-xl border border-zinc-200 bg-white p-8 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                    <flux:heading size="lg" class="mb-2">You Received a Counter-Offer</flux:heading>
                    <flux:text class="mb-6 text-zinc-500">
                        The creator has reviewed your inquiry and proposed a different rate.
                        Review the details below and choose how to respond.
                    </flux:text>

                    <div class="mb-6 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                            <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-400">Your original offer</flux:text>
                            <p class="mt-1 text-2xl font-bold text-zinc-400 line-through">
                                {{ formatMoney($booking->amount_paid) }}
                            </p>
                        </div>
                        <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-4 dark:border-indigo-700 dark:bg-indigo-950">
                            <flux:text class="text-xs font-medium uppercase tracking-wide text-indigo-600 dark:text-indigo-400">Counter-offer</flux:text>
                            <p class="mt-1 text-2xl font-bold text-indigo-700 dark:text-indigo-300">
                                {{ formatMoney($booking->counter_amount) }}
                            </p>
                        </div>
                    </div>

                    @if($booking->creator_notes)
                        <blockquote class="mb-6 border-l-4 border-indigo-300 pl-4 italic text-zinc-600 dark:border-indigo-600 dark:text-zinc-400">
                            "{{ $booking->creator_notes }}"
                        </blockquote>
                    @endif

                    @if($errorMessage)
                        <flux:callout variant="danger" icon="exclamation-triangle" class="mb-4">
                            <flux:callout.text>{{ $errorMessage }}</flux:callout.text>
                        </flux:callout>
                    @endif

                    <div class="flex flex-col gap-3 sm:flex-row">
                        <flux:button
                            wire:click="acceptCounter"
                            variant="primary"
                            icon="check"
                            class="flex-1"
                        >
                            Accept Counter-Offer
                        </flux:button>
                        <flux:button
                            wire:click="declineCounter"
                            wire:loading.attr="disabled"
                            variant="ghost"
                            icon="x-mark"
                            class="flex-1"
                        >
                            <span wire:loading.remove wire:target="declineCounter">Decline</span>
                            <span wire:loading wire:target="declineCounter">Declining…</span>
                        </flux:button>
                    </div>
                </div>
            @endif

            {{-- Requirements form --}}
            @if($step === 'requirements')
                <div class="rounded-xl border border-zinc-200 bg-white p-8 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                    <flux:heading size="lg" class="mb-2">
                        @if($inquiryToken->purpose === 'accept_counter')
                            Complete Your Booking — Counter-Offer Accepted
                        @else
                            Complete Your Booking
                        @endif
                    </flux:heading>
                    <flux:text class="mb-6 text-zinc-500">
                        @if($inquiryToken->purpose === 'accept_counter')
                            You're accepting the counter-offer of <strong class="text-zinc-700 dark:text-zinc-200">{{ formatMoney($booking->counter_amount) }}</strong>.
                            Fill in the campaign details below to proceed to payment.
                        @else
                            Your inquiry was approved at <strong class="text-zinc-700 dark:text-zinc-200">{{ formatMoney($booking->amount_paid) }}</strong>.
                            Fill in the campaign details below to proceed to payment.
                        @endif
                    </flux:text>

                    <form wire:submit="proceedToPayment" class="space-y-6">
                        @if($product->requirements->isNotEmpty())
                            <div class="space-y-5">
                                @foreach($product->requirements as $requirement)
                                    <flux:field>
                                        <flux:label>
                                            {{ $requirement->name }}
                                            @if($requirement->is_required)
                                                <span class="text-red-500">*</span>
                                            @endif
                                        </flux:label>

                                        @if($requirement->description)
                                            <flux:description>{{ $requirement->description }}</flux:description>
                                        @endif

                                        @if($requirement->type === 'textarea')
                                            <flux:textarea
                                                wire:model="requirementData.{{ $requirement->id }}"
                                                rows="3"
                                            />
                                        @else
                                            <flux:input
                                                wire:model="requirementData.{{ $requirement->id }}"
                                                :type="$requirement->type"
                                            />
                                        @endif

                                        <flux:error name="requirementData.{{ $requirement->id }}" />
                                    </flux:field>
                                @endforeach
                            </div>
                        @else
                            <flux:callout variant="info" icon="information-circle">
                                <flux:callout.text>No additional information is required. Click below to proceed to payment.</flux:callout.text>
                            </flux:callout>
                        @endif

                        @if($errorMessage)
                            <flux:callout variant="danger" icon="exclamation-triangle">
                                <flux:callout.text>{{ $errorMessage }}</flux:callout.text>
                            </flux:callout>
                        @endif

                        <flux:button
                            type="submit"
                            variant="primary"
                            class="w-full"
                            icon-trailing="arrow-right"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-75"
                        >
                            <span wire:loading.remove wire:target="proceedToPayment">Proceed to Secure Payment</span>
                            <span wire:loading wire:target="proceedToPayment">Preparing checkout…</span>
                        </flux:button>

                        @if($inquiryToken->purpose === 'accept_counter')
                            <div class="text-center">
                                <flux:button
                                    wire:click="$set('step', 'overview')"
                                    variant="ghost"
                                    size="sm"
                                >
                                    ← Back to counter-offer
                                </flux:button>
                            </div>
                        @endif
                    </form>
                </div>
            @endif
        @endif
    </div>
</div>
