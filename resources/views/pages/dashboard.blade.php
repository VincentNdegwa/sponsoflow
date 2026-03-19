<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingPayment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app'), Title('Dashboard')] class extends Component {
    public string $creatorRevenueCurrency = 'local';

    public function setCreatorRevenueCurrency(string $currency): void
    {
        if (! in_array($currency, ['local', 'usd'], true)) {
            return;
        }

        $this->creatorRevenueCurrency = $currency;
    }

    #[Computed]
    public function workspace()
    {
        return currentWorkspace();
    }

    #[Computed]
    public function isCreatorUsdView(): bool
    {
        return $this->creatorRevenueCurrency === 'usd';
    }

    #[Computed]
    public function isCreator(): bool
    {
        return isCreatorWorkspace();
    }

    #[Computed]
    public function stats(): array
    {
        $workspace = $this->workspace;

        if ($this->isCreator) {
            $base = $workspace->bookings();
            $payments = BookingPayment::query()
                ->where('status', 'completed')
                ->whereHas('booking', fn ($query) => $query->where('workspace_id', $workspace->id));

            $totalRevenueLocal = (float) (clone $payments)->sum('amount');
            $totalRevenueUsd = (float) (clone $payments)->sum('amount_usd');

            $displayCurrency = $this->isCreatorUsdView ? 'USD' : ($workspace->currency ?? 'USD');
            $displayAmount = $this->isCreatorUsdView ? $totalRevenueUsd : $totalRevenueLocal;

            return [
                [
                    'label' => 'Total Revenue',
                    'value' => formatMoney($displayAmount, $workspace, $displayCurrency),
                    'icon' => 'banknotes',
                    'sub' => $this->isCreatorUsdView ? 'Lifetime earnings (USD)' : 'Lifetime earnings (local)',
                ],
                [
                    'label' => 'Active Bookings',
                    'value' => (clone $base)->whereIn('status', [BookingStatus::CONFIRMED, BookingStatus::PROCESSING])->count(),
                    'icon' => 'arrow-path',
                    'sub' => 'Currently in progress',
                ],
                [
                    'label' => 'Open Inquiries',
                    'value' => (clone $base)->whereIn('status', [BookingStatus::INQUIRY, BookingStatus::COUNTER_OFFERED, BookingStatus::PENDING_PAYMENT])->count(),
                    'icon' => 'inbox',
                    'sub' => 'Awaiting your action',
                ],
                [
                    'label' => 'Completed',
                    'value' => (clone $base)->where('status', BookingStatus::COMPLETED)->count(),
                    'icon' => 'check-circle',
                    'sub' => 'All time',
                ],
            ];
        }

        $base = Booking::where('brand_workspace_id', $workspace->id);
        $payments = BookingPayment::query()
            ->where('status', 'completed')
            ->whereHas('booking', fn ($query) => $query->where('brand_workspace_id', $workspace->id));

        return [
            [
                'label' => 'Total Spent',
                'value' => formatMoney((float) (clone $payments)->sum('amount_usd'), $workspace, 'USD'),
                'icon' => 'banknotes',
                'sub' => 'Lifetime investment (USD)',
            ],
            [
                'label' => 'Active Campaigns',
                'value' => (clone $base)->whereIn('status', [BookingStatus::CONFIRMED, BookingStatus::PROCESSING])->count(),
                'icon' => 'arrow-path',
                'sub' => 'Currently running',
            ],
            [
                'label' => 'Open Inquiries',
                'value' => (clone $base)->whereIn('status', [BookingStatus::INQUIRY, BookingStatus::COUNTER_OFFERED])->count(),
                'icon' => 'inbox',
                'sub' => 'Pending response',
            ],
            [
                'label' => 'Completed',
                'value' => (clone $base)->where('status', BookingStatus::COMPLETED)->count(),
                'icon' => 'check-circle',
                'sub' => 'All time',
            ],
        ];
    }

    #[Computed]
    public function recentBookings()
    {
        $workspace = $this->workspace;

        if ($this->isCreator) {
            return $workspace->bookings()
                ->with(['product', 'brandUser', 'brandWorkspace', 'latestPayment'])
                ->latest()
                ->limit(6)
                ->get();
        }

        return Booking::where('brand_workspace_id', $workspace->id)
            ->with(['product', 'creator', 'workspace', 'latestPayment'])
            ->latest()
            ->limit(6)
            ->get();
    }

    #[Computed]
    public function monthlyData(): array
    {
        $workspace = $this->workspace;
        $isCreator = $this->isCreator;

        return collect(range(5, 0))->map(function ($i) use ($workspace, $isCreator) {
            $month = Carbon::now()->subMonths($i);

            if ($isCreator) {
                $count = $workspace->bookings()
                    ->whereMonth('created_at', $month->month)
                    ->whereYear('created_at', $month->year)
                    ->count();
                $payments = BookingPayment::query()
                    ->where('status', 'completed')
                    ->whereMonth('paid_at', $month->month)
                    ->whereYear('paid_at', $month->year)
                    ->whereHas('booking', fn ($query) => $query->where('workspace_id', $workspace->id));

                $revenue = $this->isCreatorUsdView
                    ? (float) (clone $payments)->sum('amount_usd')
                    : (float) (clone $payments)->sum('amount');
            } else {
                $count = Booking::where('brand_workspace_id', $workspace->id)
                    ->whereMonth('created_at', $month->month)
                    ->whereYear('created_at', $month->year)
                    ->count();
                $revenue = (float) BookingPayment::query()
                    ->where('status', 'completed')
                    ->whereMonth('paid_at', $month->month)
                    ->whereYear('paid_at', $month->year)
                    ->whereHas('booking', fn ($query) => $query->where('brand_workspace_id', $workspace->id))
                    ->sum('amount_usd');
            }

            return [
                'label' => $month->format('M'),
                'count' => $count,
                'revenue' => $revenue,
            ];
        })->toArray();
    }

    #[Computed]
    public function statusBreakdown(): array
    {
        $workspace = $this->workspace;

        $query = $this->isCreator
            ? $workspace->bookings()
            : Booking::where('brand_workspace_id', $workspace->id);

        $total = (clone $query)->count();

        if ($total === 0) {
            return [];
        }

        return collect([
            BookingStatus::COMPLETED,
            BookingStatus::PROCESSING,
            BookingStatus::CONFIRMED,
            BookingStatus::INQUIRY,
            BookingStatus::REVISION_REQUESTED,
            BookingStatus::DISPUTED,
        ])->map(function ($status) use ($query, $total) {
            $count = (clone $query)->where('status', $status)->count();

            if ($count === 0) {
                return null;
            }

            return [
                'label' => $status->label(),
                'color' => $status->badgeColor(),
                'count' => $count,
                'pct' => round(($count / $total) * 100),
            ];
        })->filter()->values()->toArray();
    }
}; ?>

<div class="pb-8">
    <div class="mb-8 flex items-start justify-between">
        <div>
            <flux:heading size="xl">
                Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }},
                {{ Auth::user()->name }}
            </flux:heading>
            <flux:subheading class="mt-1">
                @if ($this->isCreator)
                    Here's an overview of your creator business.
                @else
                    Here's an overview of your brand campaigns.
                @endif
            </flux:subheading>
        </div>

        @if ($this->isCreator)
            <div class="flex items-center gap-2 rounded-lg border border-zinc-200 bg-white p-1 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:button
                    wire:click="setCreatorRevenueCurrency('local')"
                    size="sm"
                    :variant="$this->isCreatorUsdView ? 'ghost' : 'primary'"
                >
                    Local
                </flux:button>
                <flux:button
                    wire:click="setCreatorRevenueCurrency('usd')"
                    size="sm"
                    :variant="$this->isCreatorUsdView ? 'primary' : 'ghost'"
                >
                    USD
                </flux:button>
            </div>
        @endif

        <div class="flex items-center gap-2">
            @if ($this->isCreator)
                <flux:button variant="ghost" size="sm" :href="route('bookings.index')" wire:navigate>
                    All Bookings
                </flux:button>
                <flux:button variant="primary" size="sm" icon="plus" :href="route('products.create')" wire:navigate>
                    New Product
                </flux:button>
            @else
                <flux:button variant="ghost" size="sm" :href="route('bookings.index')" wire:navigate>
                    All Campaigns
                </flux:button>
                <flux:button variant="primary" size="sm" icon="plus" :href="route('bookings.create')" wire:navigate>
                    New Booking
                </flux:button>
            @endif
        </div>
    </div>

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ($this->stats as $stat)
            <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="mb-4 flex items-center justify-between">
                    <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $stat['label'] }}</span>
                    <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                        <flux:icon :icon="$stat['icon']" class="size-4 text-zinc-500 dark:text-zinc-400" />
                    </div>
                </div>
                <div class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">
                    {{ $stat['value'] }}
                </div>
                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-500">{{ $stat['sub'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 flex flex-col gap-6">
            <div class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-center justify-between border-b border-zinc-100 px-6 py-4 dark:border-zinc-800">
                    <flux:heading size="sm">Recent Bookings</flux:heading>
                    <flux:button variant="ghost" size="sm" :href="route('bookings.index')" wire:navigate>
                        View all
                    </flux:button>
                </div>

                @if ($this->recentBookings->isEmpty())
                    <div class="flex flex-col items-center justify-center py-16 text-center">
                        <flux:icon icon="calendar-days" class="mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                        <flux:text class="font-medium text-zinc-600 dark:text-zinc-400">No bookings yet</flux:text>
                        <flux:text class="mt-1 text-sm text-zinc-400 dark:text-zinc-500">
                            @if ($this->isCreator)
                                Create a product to start receiving bookings.
                            @else
                                Browse creators and make your first booking.
                            @endif
                        </flux:text>
                    </div>
                @else
                    <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->recentBookings as $booking)
                            <a href="{{ route('bookings.show', $booking) }}" wire:navigate
                                class="flex items-center justify-between px-6 py-4 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                <div class="flex min-w-0 items-center gap-3">
                                    <div
                                        class="flex size-9 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-sm font-semibold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                        @if ($this->isCreator)
                                            {{ strtoupper(substr($booking->guest_name ?? $booking->brandUser?->name ?? '?', 0, 1)) }}
                                        @else
                                            {{ strtoupper(substr($booking->product?->name ?? '?', 0, 1)) }}
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-medium text-zinc-900 dark:text-white">
                                            @if ($this->isCreator)
                                                {{ $booking->guest_name ?? $booking->brandUser?->name ?? $booking->guest_email ?? 'Guest' }}
                                            @else
                                                {{ $booking->product?->name ?? 'Booking' }}
                                            @endif
                                        </div>
                                        <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-500">
                                            {{ $booking->created_at->diffForHumans() }}
                                        </div>
                                    </div>
                                </div>
                                <div class="flex shrink-0 items-center gap-3">
                                    <flux:badge :color="$booking->status->badgeColor()" size="sm">
                                        {{ $booking->status->label() }}
                                    </flux:badge>
                                    <span class="w-20 text-right text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                        @if ($this->isCreator)
                                            @if ($this->isCreatorUsdView)
                                                {{ formatMoney((float) ($booking->latestPayment?->amount_usd ?? 0), $this->workspace, 'USD') }}
                                            @else
                                                {{ $booking->formatAmount((float) ($booking->latestPayment?->amount ?? $booking->amount_paid)) }}
                                            @endif
                                        @else
                                            {{ formatMoney((float) ($booking->latestPayment?->amount_usd ?? 0), $this->workspace, 'USD') }}
                                        @endif
                                    </span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            @php
                $breakdown = $this->statusBreakdown;
            @endphp

            @if (count($breakdown) > 0)
                <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading size="sm" class="mb-5">Booking Status Breakdown</flux:heading>
                    <div class="space-y-3">
                        @foreach ($breakdown as $item)
                            <div class="flex items-center gap-3">
                                <div class="w-24 shrink-0 text-sm text-zinc-600 dark:text-zinc-400">
                                    {{ $item['label'] }}
                                </div>
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                    <div class="h-2 rounded-full bg-accent" style="width: {{ $item['pct'] }}%"></div>
                                </div>
                                <div class="w-10 text-right text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                    {{ $item['count'] }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="flex flex-col gap-6">
            @php
                $monthly = $this->monthlyData;
                $maxCount = max(array_column($monthly, 'count')) ?: 1;
            @endphp

            <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="sm" class="mb-1">6-Month Activity</flux:heading>
                <flux:text class="mb-5 text-xs text-zinc-400">Bookings per month</flux:text>
                <div class="flex h-32 items-end gap-1.5">
                    @foreach ($monthly as $month)
                        <div class="flex flex-1 flex-col items-center gap-1.5">
                            <div class="w-full rounded-sm bg-accent transition-all"
                                style="height: {{ $maxCount > 0 ? max(3, (int) round(($month['count'] / $maxCount) * 100)) : 3 }}px; min-height: 3px">
                            </div>
                            <span class="text-xs text-zinc-400">{{ $month['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="sm" class="mb-4">Quick Actions</flux:heading>
                <div class="flex flex-col gap-1">
                    @if ($this->isCreator)
                        <flux:button variant="ghost" icon="cube" :href="route('products.index')" wire:navigate
                            class="justify-start">
                            Manage Products
                        </flux:button>
                        <flux:button variant="ghost" icon="calendar-days" :href="route('bookings.index')" wire:navigate
                            class="justify-start">
                            View Bookings
                        </flux:button>
                        <flux:button variant="ghost" icon="credit-card" :href="route('settings.payments')"
                            wire:navigate class="justify-start">
                            Payment Setup
                        </flux:button>
                        <flux:button variant="ghost" icon="user" :href="route('profile.public')" wire:navigate
                            class="justify-start">
                            Public Profile
                        </flux:button>
                    @else
                        <flux:button variant="ghost" icon="plus-circle" :href="route('bookings.create')" wire:navigate
                            class="justify-start">
                            Create Booking
                        </flux:button>
                        <flux:button variant="ghost" icon="calendar-days" :href="route('bookings.index')" wire:navigate
                            class="justify-start">
                            My Campaigns
                        </flux:button>
                        <flux:button variant="ghost" icon="credit-card" :href="route('settings.payments')"
                            wire:navigate class="justify-start">
                            Payment Setup
                        </flux:button>
                        <flux:button variant="ghost" icon="cog-6-tooth" :href="route('profile.edit')" wire:navigate
                            class="justify-start">
                            Settings
                        </flux:button>
                    @endif
                </div>
            </div>

            @if ($this->isCreator && $this->workspace)
                @php
                    $avgRating = $this->workspace->ratings()->avg('rating');
                    $ratingCount = $this->workspace->ratings()->count();
                @endphp

                @if ($ratingCount > 0)
                    <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                        <flux:heading size="sm" class="mb-1">Your Rating</flux:heading>
                        <flux:text class="mb-4 text-xs text-zinc-400">Based on {{ $ratingCount }} review{{ $ratingCount !== 1 ? 's' : '' }}</flux:text>
                        <div class="flex items-end gap-2">
                            <span class="text-4xl font-semibold tracking-tight text-zinc-900 dark:text-white">
                                {{ number_format($avgRating, 1) }}
                            </span>
                            <span class="mb-1 text-xl text-zinc-400">/5</span>
                        </div>
                        <div class="mt-2 flex gap-0.5">
                            @for ($i = 1; $i <= 5; $i++)
                                <flux:icon
                                    icon="{{ $i <= round($avgRating) ? 'star' : 'star' }}"
                                    class="size-4 {{ $i <= round($avgRating) ? 'text-accent' : 'text-zinc-300 dark:text-zinc-600' }}" />
                            @endfor
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
