<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BookingReviewToken extends Model
{
    protected $fillable = [
        'booking_id',
        'token',
        'email',
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

    public static function generateFor(Booking $booking): self
    {
        return self::create([
            'booking_id' => $booking->id,
            'token' => Str::random(32),
            'email' => $booking->guest_email,
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function markUsed(): void
    {
        $this->update(['used_at' => now()]);
    }
}
