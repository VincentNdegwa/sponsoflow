@props(['isCreator'])

<div class="mb-6 flex flex-wrap gap-2">
    @if ($isCreator)
        <flux:button variant="ghost" size="sm" :href="route('bookings.index')" wire:navigate>
            All Bookings
        </flux:button>
        <flux:button variant="ghost" size="sm" :href="route('products.index')" wire:navigate>
            Manage Products
        </flux:button>
        <flux:button variant="ghost" size="sm" :href="route('settings.payments')" wire:navigate>
            Payment Setup
        </flux:button>
        <flux:button variant="primary" size="sm" icon="plus" :href="route('products.create')" wire:navigate>
            New Product
        </flux:button>
    @else
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
    @endif
</div>

