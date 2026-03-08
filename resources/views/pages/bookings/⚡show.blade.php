<?php

use App\Models\Booking;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app'), Title('Booking Details')] class extends Component {
    public Booking $booking;

    public function mount(Booking $booking)
    {
        if ($booking->workspace_id !== Auth::user()->currentWorkspace()->id) {
            abort(404);
        }
        
        $this->booking = $booking->load(['product', 'brandUser', 'brandWorkspace', 'slot']);
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
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-8">
            <!-- Guest/Brand Information -->
            <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
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
            </div>

            <!-- Requirements & Details -->
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

            <!-- Notes -->
            @if($booking->notes)
                <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
                    <flux:heading size="lg" class="mb-4">Notes</flux:heading>
                    
                    <flux:text class="whitespace-pre-wrap">{{ $booking->notes }}</flux:text>
                </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Status & Type -->
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
                </div>
            </div>

            <!-- Slot Information -->
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

            <!-- Timeline -->
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
</div>