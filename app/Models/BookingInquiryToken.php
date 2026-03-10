<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BookingInquiryToken extends Model
{
    protected $fillable = [
        'booking_id',
        'token',
        'email',
        'purpose',
        'expires_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function isValid(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }

    public static function generateFor(Booking $booking, string $purpose = 'respond', ?string $email = null): self
    {
        return self::create([
            'booking_id' => $booking->id,
            'token' => Str::random(48),
            'email' => $email ?? $booking->guest_email ?? $booking->brandUser?->email,
            'purpose' => $purpose,
            'expires_at' => now()->addDays(14),
        ]);
    }

    public function markUsed(): void
    {
        $this->update(['used_at' => now()]);
    }
}
