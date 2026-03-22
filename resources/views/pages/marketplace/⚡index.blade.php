<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app'), Title('Marketplace')] class extends Component {
    public function mount(): void
    {
        if (! currentWorkspace()) {
            abort(403);
        }
    }
}; ?>

<div>
    <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Marketplace</flux:heading>
            <flux:subheading>Discover campaign opportunities and manage your inquiry pipeline.</flux:subheading>
        </div>

        <div class="flex gap-2">
            <flux:button variant="ghost" :href="route('bookings.create')">New Booking</flux:button>
            <flux:button variant="primary" :href="route('campaigns.index')">View Campaigns</flux:button>
        </div>
    </div>

    <div class="rounded-2xl border border-zinc-200 bg-white p-8 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
        <flux:heading size="lg">Marketplace Home</flux:heading>
        <flux:text class="mt-2 text-zinc-500">
            This page is ready for marketplace listings and inquiry tracking. Next, we can wire campaign discovery,
            creator applications, and slot status views here.
        </flux:text>
    </div>
</div>
