<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingPayment;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app'), Title('Dashboard')] class extends Component {
    public string $creatorRevenueCurrency = 'local';

    public function setCreatorRevenueCurrency(string $currency): void
    {
        if (!in_array($currency, ['local', 'usd'], true)) {
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

        if (!$workspace) {
            return [
                [
                    'label' => 'Total Spent',
                    'value' => formatMoney(0, null, 'USD'),
                    'icon' => 'banknotes',
                    'sub' => 'Lifetime investment (USD)',
                ],
                [
                    'label' => 'Active Campaigns',
                    'value' => 0,
                    'icon' => 'arrow-path',
                    'sub' => 'Currently running',
                ],
                [
                    'label' => 'Open Inquiries',
                    'value' => 0,
                    'icon' => 'inbox',
                    'sub' => 'Pending response',
                ],
                [
                    'label' => 'Completed',
                    'value' => 0,
                    'icon' => 'check-circle',
                    'sub' => 'All time',
                ],
            ];
        }

        if ($this->isCreator) {
            $financialPayments = BookingPayment::query()
                ->select('booking_payments.*', 'bookings.status as booking_status')
                ->join('bookings', 'bookings.id', '=', 'booking_payments.booking_id')
                ->where('bookings.workspace_id', $workspace->id)
                ->whereIn('booking_payments.status', ['completed', 'refunded'])
                ->get();

            $pendingPayoutStatuses = [BookingStatus::CONFIRMED->value, BookingStatus::PROCESSING->value, BookingStatus::REVISION_REQUESTED->value];

            $availableBalanceStatuses = [BookingStatus::COMPLETED->value];

            $resolveMoney = function (BookingPayment $payment, string $localKey): float {
                if ($this->isCreatorUsdView) {
                    if ($localKey === 'gross_amount') {
                        return (float) ($payment->amount_usd ?? 0);
                    }

                    return (float) data_get($payment->amount_breakdown, 'usd.' . $localKey, 0);
                }

                if ($localKey === 'gross_amount') {
                    return (float) $payment->amount;
                }

                return (float) data_get($payment->amount_breakdown, 'local.' . $localKey, 0);
            };

            $completedPayments = $financialPayments->where('status', 'completed');

            $totalEarnings = $completedPayments->sum(fn(BookingPayment $payment): float => $resolveMoney($payment, 'gross_amount'));

            $totalFees = $completedPayments->sum(fn(BookingPayment $payment): float => $resolveMoney($payment, 'platform_fee_amount'));

            $pendingPayout = $completedPayments->whereIn('booking_status', $pendingPayoutStatuses)->whereNull('creator_released_at')->sum(fn(BookingPayment $payment): float => $resolveMoney($payment, 'creator_payout_amount'));

            $availableBalance = $completedPayments->whereIn('booking_status', $availableBalanceStatuses)->whereNull('creator_released_at')->sum(fn(BookingPayment $payment): float => $resolveMoney($payment, 'creator_payout_amount'));

            $displayCurrency = $this->isCreatorUsdView ? 'USD' : $workspace->currency ?? 'USD';

            return [
                [
                    'label' => 'Total Earnings',
                    'value' => formatMoney((float) $totalEarnings, $workspace, $displayCurrency),
                    'icon' => 'banknotes',
                    'sub' => 'Sum of successful booking gross amounts',
                ],
                [
                    'label' => 'Total Fees',
                    'value' => formatMoney((float) $totalFees, $workspace, $displayCurrency),
                    'icon' => 'scale',
                    'sub' => 'Total platform fees charged',
                ],
                [
                    'label' => 'Pending Payout',
                    'value' => formatMoney((float) $pendingPayout, $workspace, $displayCurrency),
                    'icon' => 'clock',
                    'sub' => 'Escrow held for in-progress work',
                ],
                [
                    'label' => 'Available Balance',
                    'value' => formatMoney((float) $availableBalance, $workspace, $displayCurrency),
                    'icon' => 'wallet',
                    'sub' => 'Approved and ready for release',
                ],
            ];
        }

        $base = Booking::where('brand_workspace_id', $workspace->id);
        $payments = BookingPayment::query()->where('status', 'completed')->whereHas('booking', fn($query) => $query->where('brand_workspace_id', $workspace->id));

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

        if (!$workspace) {
            return collect();
        }

        if ($this->isCreator) {
            return $workspace
                ->bookings()
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
    public function statusBreakdown(): array
    {
        $workspace = $this->workspace;

        if (!$workspace) {
            return [];
        }

        $query = $this->isCreator ? $workspace->bookings() : Booking::where('brand_workspace_id', $workspace->id);

        $total = (clone $query)->count();

        if ($total === 0) {
            return [];
        }

        return collect([BookingStatus::COMPLETED, BookingStatus::PROCESSING, BookingStatus::CONFIRMED, BookingStatus::INQUIRY, BookingStatus::REVISION_REQUESTED, BookingStatus::DISPUTED])
            ->map(function ($status) use ($query, $total) {
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
            })
            ->filter()
            ->values()
            ->toArray();
    }

    #[Computed]
    public function activitySummary(): array
    {
        $workspace = $this->workspace;

        if (! $workspace) {
            return [
                'recent' => 0,
                'active' => 0,
                'pending' => 0,
            ];
        }

        $query = $this->isCreator ? $workspace->bookings() : Booking::where('brand_workspace_id', $workspace->id);

        $recent = (clone $query)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $activeStatuses = [BookingStatus::CONFIRMED, BookingStatus::PROCESSING, BookingStatus::REVISION_REQUESTED];
        $pendingStatuses = [BookingStatus::INQUIRY, BookingStatus::COUNTER_OFFERED];

        return [
            'recent' => $recent,
            'active' => (clone $query)->whereIn('status', $activeStatuses)->count(),
            'pending' => (clone $query)->whereIn('status', $pendingStatuses)->count(),
        ];
    }

    #[Computed]
    public function recentPaymentSummary(): array
    {
        $workspace = $this->workspace;

        if (! $workspace) {
            return [
                'bookings' => 0,
                'payments' => 0,
                'total' => 0.0,
            ];
        }

        $since = now()->subDays(30);
        $isCreator = $this->isCreator;

        if ($isCreator) {
            $bookings = $workspace->bookings()->where('created_at', '>=', $since)->count();
            $payments = BookingPayment::query()
                ->where('status', 'completed')
                ->where('paid_at', '>=', $since)
                ->whereHas('booking', fn($query) => $query->where('workspace_id', $workspace->id));

            $total = $this->isCreatorUsdView ? (float) (clone $payments)->sum('amount_usd') : (float) (clone $payments)->sum('amount');
        } else {
            $bookings = Booking::where('brand_workspace_id', $workspace->id)
                ->where('created_at', '>=', $since)
                ->count();
            $payments = BookingPayment::query()
                ->where('status', 'completed')
                ->where('paid_at', '>=', $since)
                ->whereHas('booking', fn($query) => $query->where('brand_workspace_id', $workspace->id));

            $total = (float) (clone $payments)->sum('amount_usd');
        }

        return [
            'bookings' => $bookings,
            'payments' => (clone $payments)->count(),
            'total' => $total,
        ];
    }
}; ?>

<div class="pb-8">
    <x-dashboard.common.summary-header :is-creator="$this->isCreator" :is-creator-usd-view="$this->isCreatorUsdView" />
    <x-dashboard.common.stats :stats="$this->stats" />

    @if ($this->isCreator)
        <x-dashboard.creator.actions />
    @else
        <x-dashboard.brand.actions />
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 flex flex-col gap-6">
            <x-dashboard.common.recent-bookings
                :recent-bookings="$this->recentBookings"
                :is-creator="$this->isCreator"
                :is-creator-usd-view="$this->isCreatorUsdView"
                :workspace="$this->workspace"
            />

            <x-dashboard.common.booking-activity :activity-summary="$this->activitySummary" />

            @php
                $avgRating = $this->workspace?->ratings()->avg('rating') ?? 0;
                $ratingCount = $this->workspace?->ratings()->count() ?? 0;
            @endphp

            @if ($this->isCreator)
                <x-dashboard.creator.rating :avg-rating="$avgRating" :rating-count="$ratingCount" />
            @endif
        </div>

        <div class="flex flex-col gap-6">
            <x-dashboard.common.status-breakdown :breakdown="$this->statusBreakdown" />
            <x-dashboard.common.thirty-day-summary
                :summary="$this->recentPaymentSummary"
                :is-creator="$this->isCreator"
                :is-creator-usd-view="$this->isCreatorUsdView"
                :workspace="$this->workspace"
            />
        </div>
    </div>
</div>
