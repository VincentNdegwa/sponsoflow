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

        $validationErrors = app(BookingService::class)->validateRequirementData($product, $this->requirementData);
        foreach ($validationErrors as $field => $message) {
            $this->addError($field, $message);
        }
        if ($validationErrors) {
            return;
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
            <x-bookings.token-invalid />
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

                    <x-bookings.counter-offer-respond :booking="$booking" accept-action="acceptCounter" decline-action="declineCounter" :error="$errorMessage" />
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
                            You're accepting the counter-offer of <strong class="text-zinc-700 dark:text-zinc-200">{{ $booking->formatAmount((float) $booking->counter_amount) }}</strong>.
                            Fill in the campaign details below to proceed to payment.
                        @else
                            Your inquiry was approved at <strong class="text-zinc-700 dark:text-zinc-200">{{ $booking->formatAmount() }}</strong>.
                            Fill in the campaign details below to proceed to payment.
                        @endif
                    </flux:text>

                    @php $checkoutBackAction = $inquiryToken->purpose === 'accept_counter' ? '$set(\'step\', \'overview\')' : null; @endphp
                    <x-bookings.checkout-form
                        :requirements="$product->requirements"
                        action="proceedToPayment"
                        :error="$errorMessage"
                        :back-action="$checkoutBackAction"
                        back-label="← Back to counter-offer"
                    />

                </div>
            @endif
        @endif
    </div>
</div>
