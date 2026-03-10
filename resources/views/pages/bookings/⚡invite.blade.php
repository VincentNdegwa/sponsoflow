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

        foreach ($product->requirements->where('is_required', true) as $requirement) {
            if (empty($this->requirementData[$requirement->id])) {
                $this->addError("requirementData.{$requirement->id}", 'This field is required.');

                return;
            }
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
            <div class="rounded-xl border border-zinc-200 bg-white p-8 text-center shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-red-100 dark:bg-red-950">
                    <flux:icon.x-circle class="h-7 w-7 text-red-600 dark:text-red-400" />
                </div>
                <flux:heading size="xl" class="mb-2">Link Expired or Invalid</flux:heading>
                <flux:text class="text-zinc-500">
                    This invite link has already been used or has expired. Please contact the creator if you need a new one.
                </flux:text>
            </div>
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

                <form wire:submit="proceedToPayment" class="space-y-6">
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
                </form>
            </div>
        @endif
    </div>
</div>
