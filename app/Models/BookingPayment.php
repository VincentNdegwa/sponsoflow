<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Services\ExchangeRateService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class BookingPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'provider',
        'provider_transaction_id',
        'provider_reference',
        'session_id',
        'status',
        'amount',
        'creator_released_at',
        'amount_usd',
        'currency',
        'exchange_rate_to_usd',
        'exchange_rate_provider',
        'exchange_rate_fetched_at',
        'amount_breakdown',
        'provider_data',
        'metadata',
        'paid_at',
        'failed_at',
        'refunded_at',
        'failure_reason',
        'refund_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'creator_released_at' => 'datetime',
            'amount_usd' => 'decimal:2',
            'exchange_rate_to_usd' => 'decimal:10',
            'exchange_rate_fetched_at' => 'datetime',
            'amount_breakdown' => 'array',
            'provider_data' => 'array',
            'metadata' => 'array',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeRefunded($query)
    {
        return $query->where('status', 'refunded');
    }

    public function scopeForProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }

    public function isStripe(): bool
    {
        return $this->provider === 'stripe';
    }

    public function isPaystack(): bool
    {
        return $this->provider === 'paystack';
    }

    public function markAsPaid(): void
    {
        $this->update([
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        $this->booking->update(['status' => BookingStatus::CONFIRMED]);
    }

    public function markAsFailed(?string $reason = null): void
    {
        $this->update([
            'status' => 'failed',
            'failed_at' => now(),
            'failure_reason' => $reason,
        ]);
    }

    public function markAsRefunded(?string $reason = null): void
    {
        $this->update([
            'status' => 'refunded',
            'refunded_at' => now(),
            'refund_reason' => $reason,
        ]);
    }

    public static function createForBooking(Booking $booking, string $provider, array $data): self
    {
        $currency = $data['currency'] ?? 'USD';
        $amount = (float) ($data['amount'] ?? $booking->amount_paid);

        $conversionData = [
            'amount_usd' => $currency === 'USD' ? $amount : null,
            'exchange_rate_to_usd' => $currency === 'USD' ? 1 : null,
            'exchange_rate_provider' => $currency === 'USD' ? 'identity' : null,
            'exchange_rate_fetched_at' => $currency === 'USD' ? now() : null,
        ];

        try {
            $converted = app(ExchangeRateService::class)->convertToUsd($amount, $currency);
            $conversionData = [
                'amount_usd' => $converted['amount_usd'],
                'exchange_rate_to_usd' => $converted['exchange_rate_to_usd'],
                'exchange_rate_provider' => $converted['provider'],
                'exchange_rate_fetched_at' => $converted['fetched_at'],
            ];
        } catch (\Throwable $exception) {
            Log::warning('Failed to convert booking payment amount to USD at creation time', [
                'booking_id' => $booking->id,
                'provider' => $provider,
                'currency' => $currency,
                'amount' => $amount,
                'error' => $exception->getMessage(),
            ]);
        }

        return static::create(array_merge([
            'booking_id' => $booking->id,
            'provider' => $provider,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'pending',
            'amount_breakdown' => [
                'local' => [
                    'currency' => $currency,
                    'gross_amount' => $amount,
                ],
                'usd' => [
                    'currency' => 'USD',
                    'gross_amount' => $conversionData['amount_usd'],
                ],
            ],
        ], $conversionData, $data));
    }

    public static function findByProviderReference(string $provider, string $reference): ?self
    {
        return static::where('provider', $provider)
            ->where('provider_reference', $reference)
            ->first();
    }

    public static function findByProviderTransactionId(string $provider, string $transactionId): ?self
    {
        return static::where('provider', $provider)
            ->where('provider_transaction_id', $transactionId)
            ->first();
    }

    public static function findBySessionId(string $provider, string $sessionId): ?self
    {
        return static::where('provider', $provider)
            ->where('session_id', $sessionId)
            ->first();
    }
}
