@props(['booking', 'isBrandUser' => false])

@php
    $payments = $booking->payments->sortByDesc('created_at')->values();
    $statusColor = static fn (string $status): string => match ($status) {
        'completed' => 'green',
        'pending' => 'amber',
        'failed' => 'red',
        'refunded' => 'zinc',
        default => 'zinc',
    };
@endphp

<div {{ $attributes->merge(['class' => 'rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800']) }}>
    <div class="mb-4 flex items-start justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="rounded-lg bg-zinc-100 p-2 dark:bg-zinc-900">
                <flux:icon icon="credit-card" class="size-5 text-accent-content" />
            </div>
            <div>
                <flux:heading size="lg">Payment History</flux:heading>
                <flux:text class="text-xs text-zinc-500">Financial activity and settlement signals</flux:text>
            </div>
        </div>

        <flux:badge size="sm" color="zinc">{{ $payments->count() }} {{ \Illuminate\Support\Str::plural('payment', $payments->count()) }}</flux:badge>
    </div>

    @if($payments->isEmpty())
        <flux:text class="text-sm text-zinc-500">No payment records yet.</flux:text>
    @else
        <div class="max-h-[30rem] space-y-4 overflow-y-auto pr-1">
            @foreach($payments as $payment)
                @php
                    $originalCurrency = $payment->currency ?? $booking->currency ?? 'USD';
                    $originalAmount = (float) $payment->amount;
                    $usdAmount = $payment->amount_usd !== null ? (float) $payment->amount_usd : null;
                    $displayUsd = $isBrandUser && $usdAmount !== null;
                    $primaryAmount = $displayUsd
                        ? \App\Support\CurrencySupport::formatCurrency($usdAmount, 'USD')
                        : \App\Support\CurrencySupport::formatCurrency($originalAmount, $originalCurrency);
                    $showSecondary = $displayUsd && $originalCurrency !== 'USD';
                    $creatorPayoutLocal = (float) data_get($payment->amount_breakdown, 'local.creator_payout_amount', 0);
                    $creatorPayoutUsd = (float) data_get($payment->amount_breakdown, 'usd.creator_payout_amount', 0);
                @endphp

                <div class="rounded-xl border border-zinc-200 bg-zinc-50/60 p-4 dark:border-zinc-700 dark:bg-zinc-900/40">
                    <div class="flex items-start justify-between gap-3">
                        <div class="relative pl-5">
                            <span class="absolute left-0 top-2 inline-flex size-2 rounded-full bg-accent"></span>
                            <div class="flex items-center gap-2">
                                <flux:text class="font-medium capitalize">{{ $payment->provider }}</flux:text>
                                <flux:badge size="sm" :color="$statusColor($payment->status)">{{ ucfirst($payment->status) }}</flux:badge>
                            </div>
                            <flux:text class="mt-1 text-xs text-zinc-500">
                                {{ formatWorkspaceDate($payment->created_at) }} {{ formatWorkspaceTime($payment->created_at) }}
                            </flux:text>
                            @if($payment->provider_reference)
                                <flux:text class="mt-1 text-xs text-zinc-500">Ref: {{ $payment->provider_reference }}</flux:text>
                            @endif
                        </div>

                        <div class="text-right">
                            <flux:text class="text-base font-semibold">{{ $primaryAmount }}</flux:text>
                            @if($showSecondary)
                                <flux:text class="text-xs text-zinc-500">
                                    {{ \App\Support\CurrencySupport::formatCurrency($originalAmount, $originalCurrency) }}
                                </flux:text>
                            @endif
                        </div>
                    </div>

                    @if(! $isBrandUser)
                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            <div class="rounded-md bg-zinc-50 px-3 py-2 dark:bg-zinc-900">
                                <flux:text class="text-xs text-zinc-500">Creator Payout (Local)</flux:text>
                                <flux:text class="font-medium">
                                    {{ \App\Support\CurrencySupport::formatCurrency($creatorPayoutLocal, $originalCurrency) }}
                                </flux:text>
                            </div>
                            @if($creatorPayoutUsd > 0)
                                <div class="rounded-md bg-zinc-50 px-3 py-2 dark:bg-zinc-900">
                                    <flux:text class="text-xs text-zinc-500">Creator Payout (USD)</flux:text>
                                    <flux:text class="font-medium">{{ \App\Support\CurrencySupport::formatCurrency($creatorPayoutUsd, 'USD') }}</flux:text>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
