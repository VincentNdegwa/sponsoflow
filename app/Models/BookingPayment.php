<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'currency',
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

    public function markAsFailed(string $reason = null): void
    {
        $this->update([
            'status' => 'failed',
            'failed_at' => now(),
            'failure_reason' => $reason,
        ]);
    }

    public function markAsRefunded(string $reason = null): void
    {
        $this->update([
            'status' => 'refunded',
            'refunded_at' => now(),
            'refund_reason' => $reason,
        ]);
    }

    public static function createForBooking(Booking $booking, string $provider, array $data): self
    {
        return static::create(array_merge([
            'booking_id' => $booking->id,
            'provider' => $provider,
            'amount' => $booking->amount_paid,
            'currency' => $data['currency'] ?? 'USD',
            'status' => 'pending',
        ], $data));
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
