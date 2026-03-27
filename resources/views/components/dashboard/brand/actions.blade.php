<div class="mb-6 flex flex-wrap gap-2">
    <flux:button variant="ghost" size="sm" :href="route('campaigns.index')" wire:navigate>
        All Campaigns
    </flux:button>
    <flux:button variant="ghost" size="sm" :href="route('bookings.index')" wire:navigate>
        All Bookings
    </flux:button>
    <flux:button variant="ghost" size="sm" :href="route('settings.payments')" wire:navigate>
        Payment Setup
    </flux:button>
    <flux:button variant="primary" size="sm" icon="plus" :href="route('bookings.create')" wire:navigate>
        New Booking
    </flux:button>
</div>

