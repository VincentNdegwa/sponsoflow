<?php

use App\Models\Product;
use App\Models\Slot;
use App\Enums\SlotStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Carbon\Carbon;

new #[Layout('layouts::app'), Title('Product Calendar')] class extends Component {
    public Product $product;
    public string $currentMonth;
    public string $currentYear;

    public function mount(Product $product): void
    {
        if ($product->workspace_id !== Auth::user()->currentWorkspace()->id) {
            abort(404);
        }

        $this->product = $product;
        $this->currentMonth = now()->format('m');
        $this->currentYear = now()->format('Y');
    }

    public function previousMonth(): void
    {
        $date = Carbon::createFromDate($this->currentYear, $this->currentMonth, 1)->subMonth();
        $this->currentMonth = $date->format('m');
        $this->currentYear = $date->format('Y');
    }

    public function nextMonth(): void
    {
        $date = Carbon::createFromDate($this->currentYear, $this->currentMonth, 1)->addMonth();
        $this->currentMonth = $date->format('m');
        $this->currentYear = $date->format('Y');
    }

    public function goToToday(): void
    {
        $this->currentMonth = now()->format('m');
        $this->currentYear = now()->format('Y');
    }

    #[Computed]
    public function currentDate(): Carbon
    {
        return Carbon::createFromDate($this->currentYear, $this->currentMonth, 1);
    }

    #[Computed]
    public function availableSlots()
    {
        $startDate = $this->currentDate->copy()->startOfMonth();
        $endDate = $this->currentDate->copy()->endOfMonth();

        return $this->product
            ->slots()
            ->where('status', SlotStatus::Available)
            ->whereBetween('slot_date', [$startDate, $endDate])
            ->orderBy('slot_date')
            ->get()
            ->groupBy(function ($slot) {
                return $slot->slot_date->format('Y-m-d');
            });
    }

    #[Computed]
    public function calendarDays(): array
    {
        $firstDay = $this->currentDate->copy()->startOfMonth();
        $lastDay = $this->currentDate->copy()->endOfMonth();

        $startDate = $firstDay->copy()->startOfWeek(Carbon::MONDAY);
        $endDate = $lastDay->copy()->endOfWeek(Carbon::SUNDAY);

        $days = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $days[] = [
                'date' => $currentDate->copy(),
                'isCurrentMonth' => $currentDate->month === (int) $this->currentMonth,
                'isToday' => $currentDate->isToday(),
                'slots' => $this->availableSlots[$currentDate->format('Y-m-d')] ?? collect(),
            ];
            $currentDate->addDay();
        }

        return $days;
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-start justify-between">
        <div>
            <div class="mb-2 flex items-center gap-3">
                <flux:heading size="xl">{{ $product->name }} - Calendar</flux:heading>
                <flux:badge variant="{{ $product->is_active ? 'lime' : 'zinc' }}">
                    {{ $product->is_active ? 'Active' : 'Inactive' }}
                </flux:badge>
            </div>
            <flux:subheading>Available slots calendar view</flux:subheading>
        </div>

        <div class="flex gap-2">
            <flux:dropdown>
                <flux:button variant="ghost" icon="ellipsis-horizontal">Actions</flux:button>
                <flux:menu>
                    <flux:menu.item :href="route('products.show', $product)" icon="arrow-left">Back to Product</flux:menu.item>
                    <flux:menu.item :href="route('products.index')" icon="squares-2x2">All Products</flux:menu.item>
                </flux:menu>
            </flux:dropdown>
            <flux:button :href="route('products.show', $product)" variant="primary" icon="plus">
                Add Slot
            </flux:button>
        </div>
    </div>

    <div
        class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800 overflow-hidden shadow-sm">
        <div class="flex items-center justify-between border-b border-zinc-200 p-4 dark:border-zinc-700">
            <flux:button wire:click="previousMonth" variant="primary" icon="chevron-left" size="sm">
                Previous
            </flux:button>

            <div class="flex items-center gap-4">
                <flux:heading size="lg">
                    {{ formatWorkspaceDate($this->currentDate) }}
                </flux:heading>
                <flux:button wire:click="goToToday" variant="ghost" size="sm">
                    Today
                </flux:button>
            </div>

            <flux:button wire:click="nextMonth" variant="primary" icon-trailing="chevron-right" size="sm">
                Next
            </flux:button>
        </div>

        <div class="overflow-x-auto">
            <div class="grid w-full min-w-[800px]" style="grid-template-columns: repeat(7, minmax(0, 1fr));">

                @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dayName)
                    <div
                        class="py-3 text-center border-b border-r border-zinc-200 dark:border-zinc-700 font-semibold text-xs uppercase tracking-wider">
                        {{ $dayName }}
                    </div>
                @endforeach

                @foreach ($this->calendarDays as $day)
                    <div
                        class="min-h-[140px] p-2 border-b border-r border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 {{ !$day['isCurrentMonth'] ? 'bg-zinc-50/50 dark:bg-zinc-900/20 text-zinc-400' : '' }}">
                        <div class="flex justify-between items-start mb-2">
                            @if ($day['isToday'])
                                <span
                                    class="flex items-center justify-center w-7 h-7 bg-blue-600 text-white rounded-full text-xs font-bold shadow-sm">
                                    {{ $day['date']->format('j') }}
                                </span>
                            @else
                                <span
                                    class="text-sm font-medium p-1 {{ $day['isCurrentMonth'] ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-400 dark:text-zinc-600' }}">
                                    {{ $day['date']->format('j') }}
                                </span>
                            @endif
                        </div>

                        <div class="space-y-1">
                            @foreach ($day['slots'] as $slot)
                                <div
                                    class="bg-green-100 dark:bg-green-900/40 border border-green-200 dark:border-green-800 rounded px-1.5 py-1 text-[10px] leading-tight shadow-xs">
                                    <div class="flex items-center justify-between gap-1">
                                        <span class="text-green-800 dark:text-green-300 font-bold truncate">
                                            {{ $slot->slot_time ? formatWorkspaceTime($slot->slot_time) : 'Available' }}
                                        </span>
                                        <span class="text-green-700 dark:text-green-400">
                                            {{ formatMoney((float) $slot->price, $product->workspace) }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="grid gap-4 mt-8" style="grid-template-columns: repeat(3, minmax(0, 1fr));">
        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800 shadow-sm">
            <flux:text class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Available This Month
            </flux:text>
            <flux:heading size="lg" class="mt-1 text-blue-600 dark:text-blue-400">
                {{ $this->availableSlots->flatten()->count() }} Slots
            </flux:heading>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800 shadow-sm">
            <flux:text class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Potential Revenue
            </flux:text>
            <flux:heading size="lg" class="mt-1 text-green-600 dark:text-green-400">
                {{ formatMoney($this->availableSlots->flatten()->sum('price')) }}
            </flux:heading>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800 shadow-sm">
            <flux:text class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Active Days</flux:text>
            <flux:heading size="lg" class="mt-1 text-sky-600 dark:text-sky-400">
                {{ $this->availableSlots->count() }} Days
            </flux:heading>
        </div>
    </div>

</div>
