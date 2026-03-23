<?php

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Livewire\Component;

new class extends Component {
    public string $mode = 'sidebar';

    /** @var array<int, array<string, mixed>> */
    public array $notifications = [];

    public int $unreadCount = 0;

    public function mount(string $mode = 'sidebar'): void
    {
        $this->mode = $mode;
        $this->loadNotifications();
    }

    public function loadNotifications(): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $items = $user->notifications()->latest()->take(30)->get();

        $this->unreadCount = $items->whereNull('read_at')->count();

        $this->notifications = $items->map(fn (DatabaseNotification $n) => [
            'id' => $n->id,
            'type' => $n->data['type'] ?? 'notification',
            'data' => $n->data,
            'read_at' => $n->read_at?->toDateTimeString(),
            'created_at' => $n->created_at->diffForHumans(),
        ])->toArray();
    }

    public function markAllRead(): void
    {
        auth()->user()?->unreadNotifications()->update(['read_at' => now()]);
        $this->unreadCount = 0;

        $this->notifications = array_map(function (array $n): array {
            $n['read_at'] = now()->toDateTimeString();

            return $n;
        }, $this->notifications);
    }

    public function markRead(string $id): void
    {
        $notification = auth()->user()?->notifications()->where('id', $id)->first();

        if (! $notification) {
            return;
        }

        $notification->markAsRead();

        $bookingId = $notification->data['booking_id'] ?? null;

        $this->loadNotifications();

        if ($bookingId) {
            $this->redirect(route('bookings.show', $bookingId), navigate: true);
        }
    }

    /** @return array<string, string> */
    private function notificationMeta(string $type): array
    {
        return match ($type) {
            'inquiry_received' => ['icon' => 'bell', 'label' => 'New Inquiry', 'color' => 'text-indigo-500'],
            'payment_received' => ['icon' => 'banknotes', 'label' => 'Payment Received', 'color' => 'text-green-500'],
            'work_submitted' => ['icon' => 'arrow-up-tray', 'label' => 'Work Submitted', 'color' => 'text-blue-500'],
            'work_approved' => ['icon' => 'check-circle', 'label' => 'Work Approved', 'color' => 'text-green-500'],
            'revision_requested' => ['icon' => 'arrow-path', 'label' => 'Revision Requested', 'color' => 'bg-accent-500'],
            'dispute_opened' => ['icon' => 'shield-exclamation', 'label' => 'Dispute Opened', 'color' => 'text-red-500'],
            'booking_invite' => ['icon' => 'envelope', 'label' => 'Booking Invite', 'color' => 'text-violet-500'],
            default => ['icon' => 'bell', 'label' => 'Notification', 'color' => 'text-zinc-500'],
        };
    }
};
?>

<div
    x-data
    x-init="$nextTick(() => {
        if (window.Echo) {
            // Request browser notification permission proactively
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission();
            }

            window.Echo.private('App.Models.User.{{ auth()->id() }}')
                .notification((payload) => {
                    $wire.loadNotifications();
                    $store.notifPanel.hasNew = true;

                    // Build a readable label from the payload type
                    const labels = {
                        inquiry_received:  'New Inquiry',
                        payment_received:  'Payment Received',
                        work_submitted:    'Work Submitted',
                        work_approved:     'Work Approved',
                        revision_requested:'Revision Requested',
                        dispute_opened:    'Dispute Opened',
                        booking_invite:    'Booking Invite',
                    };
                    const label  = labels[payload.type] ?? 'New Notification';
                    const body   = payload.product_name ?? '';

                    // In-app toast
                    $store.notifPanel.addToast(label, body);

                    // Browser notification when tab is hidden
                    if (document.hidden && 'Notification' in window && Notification.permission === 'granted') {
                        new Notification(label, {
                            body: body,
                            icon: '/favicon.ico',
                        });
                    }
                });
        }
    })"
    @keydown.escape.window="$store.notifPanel.open = false"
    @close-notification-panel.window="$store.notifPanel.open = false"
    class="relative"
>

    {{-- Bell trigger --}}
    @if($mode === 'sidebar')
        <flux:sidebar.item
            icon="bell"
            @click="$store.notifPanel.open = !$store.notifPanel.open; $store.notifPanel.hasNew = false"
            badgeColor="amber"
            :badge="$unreadCount > 0 ? ($unreadCount > 99 ? '99+' : (string) $unreadCount) : null"
            x-bind:class="{ 'notif-glow': $store.notifPanel.hasNew }"
        >
            {{ __('Notifications') }}
        </flux:sidebar.item>
    @else
        <button
            @click="$store.notifPanel.open = !$store.notifPanel.open; $store.notifPanel.hasNew = false"
            class="relative flex h-9 w-9 items-center justify-center rounded-md text-zinc-500 hover:bg-zinc-200 hover:text-zinc-700 dark:hover:bg-zinc-700 dark:hover:text-zinc-200 focus:outline-none"
            x-bind:class="{ 'notif-glow': $store.notifPanel.hasNew }"
            aria-label="{{ __('Notifications') }}"
        >
            <flux:icon.bell class="h-5 w-5" />
            @if($unreadCount > 0)
                <span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-0.5 text-[10px] font-bold leading-none text-white">
                    {{ $unreadCount > 99 ? '99+' : $unreadCount }}
                </span>
            @endif
        </button>
    @endif

    {{-- Notification panel --}}
    <div
        x-show="$store.notifPanel.open"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-x-4"
        x-transition:enter-end="opacity-100 translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-x-0"
        x-transition:leave-end="opacity-0 translate-x-4"
        @click.outside="$store.notifPanel.open = false"
        class="fixed top-0 z-50 flex h-screen flex-col border-l border-zinc-200 bg-white shadow-2xl dark:border-zinc-700 dark:bg-zinc-900 {{ $mode === 'sidebar' ? 'right-0 w-full sm:w-96 lg:left-64 lg:right-auto lg:w-96' : 'right-0 w-full sm:w-96' }}"
        x-effect="if (open) $store.notifPanel.hasNew = false"
    >
        {{-- Header --}}
        <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
            <flux:heading size="lg">Notifications</flux:heading>
            <div class="flex items-center gap-2">
                @if($unreadCount > 0)
                    <flux:button wire:click="markAllRead" variant="ghost" size="sm">
                        Mark all read
                    </flux:button>
                @endif
                <button
                    @click="$store.notifPanel.open = false"
                    class="rounded-md p-1 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200"
                >
                    <flux:icon.x-mark class="h-5 w-5" />
                </button>
            </div>
        </div>

        {{-- List --}}
        <div class="flex-1 overflow-y-auto">
            @forelse($notifications as $notification)
                @php
                    $meta = $this->notificationMeta($notification['type']);
                    $bookingId = $notification['data']['booking_id'] ?? null;
                    $isRead = ! empty($notification['read_at']);
                @endphp
                <a
                    href="{{ $bookingId ? route('bookings.show', $bookingId) : '#' }}"
                    wire:click.prevent="markRead('{{ $notification['id'] }}')"
                    @click="$store.notifPanel.open = false"
                    class="flex items-start gap-4 border-b border-zinc-100 px-5 py-4 transition hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/50 {{ $isRead ? 'opacity-60' : '' }}"
                >
                    <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                        <flux:icon :name="$meta['icon']" class="h-5 w-5 {{ $meta['color'] }}" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $meta['label'] }}</p>
                            @if(! $isRead)
                                <span class="mt-1 me-0.5 h-2 w-2 shrink-0 rounded-full bg-indigo-500"></span>
                            @endif
                        </div>
                        <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $notification['data']['product_name'] ?? '' }}
                            @if(! empty($notification['data']['reason']))
                                — {{ Str::limit($notification['data']['reason'], 60) }}
                            @endif
                        </p>
                        <p class="mt-1 text-xs text-zinc-400">{{ $notification['created_at'] }}</p>
                    </div>
                </a>
            @empty
                <div class="flex flex-col items-center justify-center gap-3 px-5 py-16 text-center">
                    <flux:icon.bell class="h-10 w-10 text-zinc-300 dark:text-zinc-600" />
                    <flux:text class="text-zinc-400">No notifications yet.</flux:text>
                </div>
            @endforelse
        </div>

        {{-- Showing latest hint --}}
        @if(count($notifications) >= 30)
            <div class="border-t border-zinc-200 px-5 py-3 dark:border-zinc-700">
                <flux:text class="text-center text-xs text-zinc-400">Showing latest 30 notifications</flux:text>
            </div>
        @endif
    </div>
</div>
