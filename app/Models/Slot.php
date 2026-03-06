<?php

namespace App\Models;

use App\Enums\SlotStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Slot extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'workspace_id',
        'booked_by_user_id',
        'slot_date',
        'slot_time',
        'price',
        'status',
        'reserved_until',
        'brand_assets',
        'notes',
        'proof_url',
        'proof_submitted_at',
        'completed_at',
        'reserved_at',
        'reserved_by',
        'stripe_session_id',
    ];

    protected function casts(): array
    {
        return [
            'slot_date' => 'date',
            'slot_time' => 'datetime:H:i',
            'price' => 'decimal:2',
            'status' => SlotStatus::class,
            'reserved_until' => 'datetime',
            'brand_assets' => 'array',
            'proof_submitted_at' => 'datetime',
            'completed_at' => 'datetime',
            'reserved_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', SlotStatus::Available);
    }

    public function scopeBooked($query)
    {
        return $query->where('status', SlotStatus::Booked);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', SlotStatus::Completed);
    }

    public function scopeForWorkspace($query, Workspace $workspace)
    {
        return $query->where('workspace_id', $workspace->id);
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('slot_date', $date);
    }

    public function scopeNotReserved($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('reserved_at')
                ->orWhere('reserved_at', '<', now()->subMinutes(15));
        });
    }

    public function scopeExpiredReservations($query)
    {
        return $query->whereNotNull('reserved_at')
            ->where('reserved_at', '<', now()->subMinutes(15))
            ->where('status', SlotStatus::Available);
    }

    public function booking()
    {
        return $this->hasOne(Booking::class);
    }

    public function isReserved(): bool
    {
        return $this->reserved_at && $this->reserved_at->isAfter(now()->subMinutes(15));
    }

    public function reserveFor(string $sessionId, ?string $email = null): void
    {
        $this->update([
            'reserved_at' => now(),
            'reserved_by' => $email ?: $sessionId,
            'stripe_session_id' => $sessionId,
        ]);
    }

    public function clearReservation(): void
    {
        $this->update([
            'reserved_at' => null,
            'reserved_by' => null,
            'stripe_session_id' => null,
        ]);
    }

    public function canTransitionTo(SlotStatus $status): bool
    {
        return $this->status->canTransitionTo($status);
    }
}
