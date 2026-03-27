<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\CampaignSlotStatus;
use App\Enums\SlotStatus;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\ExchangeRate;
use App\Notifications\PaymentReceivedNotification;
use Illuminate\Support\Facades\Log;

class PaymentCompletionService
{
    public function __construct(
        protected BookingShadowCampaignService $bookingShadowCampaignService,
        protected GuestAccountCreationService $guestAccountCreationService,
    ) {}

    /**
     * @param  array<string, mixed>  $paymentUpdate
     * @param  array<string, mixed>  $logContext
     */
    public function finalizePayment(BookingPayment $payment, array $paymentUpdate, array $logContext = []): void
    {
        if ($payment->isCompleted()) {
            return;
        }

        $payment->update($paymentUpdate);

        $booking = $payment->booking;

        if (! $booking) {
            return;
        }

        $booking->update(['status' => BookingStatus::CONFIRMED]);

        if ($booking->slot) {
            $booking->slot->update([
                'status' => SlotStatus::Booked,
                'reserved_until' => null,
            ]);
        }

        if ($booking->isMarketplaceApplication() && $booking->campaignSlot) {
            $booking->campaignSlot->update([
                'status' => CampaignSlotStatus::Active,
            ]);
        }

        $this->handleGuestAccountCreation($booking);
        $this->bookingShadowCampaignService->activateForPaidInquiry($booking->fresh());
        $this->notifyCreatorPaymentReceived($booking, $payment);

        Log::info('Payment confirmed for booking', array_merge([
            'booking_id' => $payment->booking_id,
            'payment_id' => $payment->id,
        ], $logContext));
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveUsdConversion(BookingPayment $payment, float $paidAmount, string $reference): array
    {
        if (strtoupper((string) $payment->currency) === 'USD') {
            return [
                'amount_usd' => $paidAmount,
                'exchange_rate_to_usd' => 1.0,
                'provider' => 'identity',
                'fetched_at' => now(),
            ];
        }

        $storedRate = (float) ($payment->exchange_rate_to_usd ?? 0);

        if ($storedRate > 0) {
            return [
                'amount_usd' => round($paidAmount * $storedRate, 2),
                'exchange_rate_to_usd' => $storedRate,
                'provider' => $payment->exchange_rate_provider,
                'fetched_at' => $payment->exchange_rate_fetched_at,
            ];
        }

        try {
            return app(ExchangeRateService::class)->convertToUsd($paidAmount, (string) $payment->currency);
        } catch (\Throwable $exception) {
            Log::warning('Unable to convert payment amount to USD via configured provider', [
                'payment_id' => $payment->id,
                'reference' => $reference,
                'currency' => $payment->currency,
                'error' => $exception->getMessage(),
            ]);
        }

        $fallbackRate = ExchangeRate::query()
            ->where('base_currency', strtoupper((string) $payment->currency))
            ->where('target_currency', 'USD')
            ->latest('fetched_at')
            ->first();

        if ($fallbackRate && (float) $fallbackRate->rate > 0) {
            $rate = (float) $fallbackRate->rate;

            return [
                'amount_usd' => round($paidAmount * $rate, 2),
                'exchange_rate_to_usd' => $rate,
                'provider' => $fallbackRate->provider,
                'fetched_at' => $fallbackRate->fetched_at,
            ];
        }

        return [
            'amount_usd' => $payment->amount_usd,
            'exchange_rate_to_usd' => $payment->exchange_rate_to_usd,
            'provider' => $payment->exchange_rate_provider,
            'fetched_at' => $payment->exchange_rate_fetched_at,
        ];
    }

    /**
     * @param  array<string, mixed>  $conversion
     * @return array<string, mixed>
     */
    public function normalizeUsdConversion(array $conversion, float $paidAmount): array
    {
        $effectiveRate = (float) ($conversion['exchange_rate_to_usd'] ?? 0);
        $amountUsd = $conversion['amount_usd'] ?? null;

        if ($effectiveRate <= 0 && is_numeric($amountUsd) && $paidAmount > 0) {
            $effectiveRate = round(((float) $amountUsd) / $paidAmount, 10);
            $conversion['exchange_rate_to_usd'] = $effectiveRate;
        }

        if (! is_numeric($amountUsd) && $effectiveRate > 0) {
            $conversion['amount_usd'] = round($paidAmount * $effectiveRate, 2);
        }

        return $conversion;
    }

    /**
     * @param  array<string, mixed>  $localBreakdown
     * @return array<string, mixed>
     */
    public function convertBreakdownToUsd(array $localBreakdown, float $exchangeRateToUsd): array
    {
        $numericKeys = [
            'gross_amount',
            'platform_fee_amount',
            'processor_fee_amount',
            'escrow_amount',
            'creator_payout_amount',
            'requested_amount',
            'creator_released_amount',
            'refunded_amount',
            'platform_fee_refunded_amount',
            'creator_payout_reversed_amount',
        ];

        $usdBreakdown = ['currency' => 'USD'];

        foreach ($numericKeys as $key) {
            $value = data_get($localBreakdown, $key);

            if ($value === null || ! is_numeric($value) || $exchangeRateToUsd <= 0) {
                $usdBreakdown[$key] = null;

                continue;
            }

            $usdBreakdown[$key] = round((float) $value * $exchangeRateToUsd, 2);
        }

        return $usdBreakdown;
    }

    private function handleGuestAccountCreation(Booking $booking): void
    {
        if (! $booking->brand_user_id && $booking->guest_email && $booking->guest_name) {
            $this->guestAccountCreationService->createAccountForGuest($booking);
        }
    }

    private function notifyCreatorPaymentReceived(Booking $booking, BookingPayment $payment): void
    {
        if (! $booking->creator) {
            return;
        }

        try {
            $booking->creator->notify(new PaymentReceivedNotification($booking));
        } catch (\Throwable $exception) {
            Log::warning('Failed to dispatch creator payment notification', [
                'booking_id' => $payment->booking_id,
                'payment_id' => $payment->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
