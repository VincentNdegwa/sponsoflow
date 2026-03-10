<?php

use App\Models\BookingInviteToken;
use App\Services\BookingService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::guest'), Title('Complete Your Booking')] class extends Component {
    public ?BookingInviteToken $inviteToken = null;
    public bool $tokenInvalid = false;
    public bool $isProcessing = false;
    public ?string $errorMessage = null;

    public array $requirementData = [];

    // Guest-only fields (shown when not authenticated)
    public string $guestName = '';
    public string $guestEmail = '';
    public string $guestCompany = '';

    public function mount(string $token): void
    {
        $this->inviteToken = BookingInviteToken::where('token', $token)
            ->with(['booking.product.requirements', 'booking.product.workspace', 'booking.creator'])
            ->first();

        if (! $this->inviteToken || ! $this->inviteToken->isValid()) {
            $this->tokenInvalid = true;
        }
    }

    public function proceedToPayment(): void
    {
        if (! $this->inviteToken || ! $this->inviteToken->isValid()) {
            $this->errorMessage = 'This invite link has expired or already been used.';

            return;
        }

        $booking = $this->inviteToken->booking;
        $product = $booking->product;

        $isAuthenticated = Auth::check();

        if (! $isAuthenticated) {
            $this->validate([
                'guestName' => 'required|string|max:255',
                'guestEmail' => 'required|email|max:255',
                'guestCompany' => 'nullable|string|max:255',
            ]);
        }

        $validationErrors = app(BookingService::class)->validateRequirementData($product, $this->requirementData);
        foreach ($validationErrors as $field => $message) {
            $this->addError($field, $message);
        }
        if ($validationErrors) {
            return;
        }

        $this->isProcessing = true;
        $this->errorMessage = null;

        try {
            if ($isAuthenticated) {
                $workspace = currentWorkspace();
                $brandData = [
                    'brand_user_id' => Auth::id(),
                    'brand_workspace_id' => $workspace?->id,
                ];
            } else {
                $brandData = [
                    'brand_user_id' => null,
                    'guest_name' => $this->guestName,
                    'guest_email' => $this->guestEmail,
                    'guest_company' => $this->guestCompany ?: null,
                ];
            }

            $result = app(BookingService::class)->fulfillInviteBooking(
                $this->inviteToken,
                $this->requirementData,
                $brandData,
            );

            if ($result['success']) {
                $this->inviteToken->markUsed();
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

        @if($tokenInvalid)
            <x-bookings.token-invalid message="This invite link has already been used or has expired. Please contact the creator if you need a new one." />
        @elseif($inviteToken)
            @php $booking = $inviteToken->booking; $product = $booking->product; @endphp

            {{-- Creator branding header --}}
            <div class="mb-8 text-center">
                <flux:heading size="xl">
                    {{ $product->workspace->name }}
                </flux:heading>
                <flux:text class="mt-1 text-zinc-500">Collaboration proposal for you</flux:text>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-8 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">

                {{-- Booking summary --}}
                <div class="mb-6 rounded-lg border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">Product</flux:text>
                            <p class="mt-1 font-semibold text-zinc-900 dark:text-white">{{ $product->name }}</p>
                            @if($product->description)
                                <p class="mt-1 text-sm text-zinc-500">{{ $product->description }}</p>
                            @endif
                        </div>
                        <div>
                            <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">Amount</flux:text>
                            <p class="mt-1 text-2xl font-bold text-accent-content">{{ $booking->formatAmount() }}</p>
                        </div>
                    </div>

                    @if($booking->notes)
                        <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                            <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">Note from creator</flux:text>
                            <blockquote class="mt-1 italic text-zinc-600 dark:text-zinc-400">"{{ $booking->notes }}"</blockquote>
                        </div>
                    @endif
                </div>

                {{-- Requirements form --}}
                <flux:heading size="lg" class="mb-2">Complete Your Booking</flux:heading>
                <flux:text class="mb-6 text-zinc-500">
                    @if($product->requirements->isNotEmpty())
                        Fill in the campaign details below, then proceed to secure payment.
                    @else
                        Click below to proceed to secure payment.
                    @endif
                </flux:text>

                <x-bookings.checkout-form :requirements="$product->requirements" action="proceedToPayment" :error="$errorMessage">
                    <x-slot:guest-fields>
                        @guest
                            <div class="space-y-5 rounded-lg border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-900">
                                <flux:heading size="sm" class="mb-1">Your Details</flux:heading>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <flux:field>
                                        <flux:label>Full name *</flux:label>
                                        <flux:input wire:model="guestName" placeholder="Jane Smith" />
                                        <flux:error name="guestName" />
                                    </flux:field>
                                    <flux:field>
                                        <flux:label>Email *</flux:label>
                                        <flux:input wire:model="guestEmail" type="email" placeholder="jane@brand.com" />
                                        <flux:error name="guestEmail" />
                                    </flux:field>
                                </div>
                                <flux:field>
                                    <flux:label>Company (optional)</flux:label>
                                    <flux:input wire:model="guestCompany" placeholder="Acme Corp" />
                                </flux:field>
                            </div>
                        @endguest
                    </x-slot:guest-fields>
                </x-bookings.checkout-form>
            </div>
        @endif
    </div>
</div>
