<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Booking extends Model
{
    protected $fillable = [
        'slot_id',
        'product_id',
        'creator_id',
        'brand_user_id',
        'brand_workspace_id',
        'guest_email',
        'guest_name',
        'guest_company',
        'requirement_data',
        'amount_paid',
        'stripe_payment_intent_id',
        'stripe_session_id',
        'status',
        'account_claimed',
        'claimed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'requirement_data' => 'array',
            'amount_paid' => 'decimal:2',
            'account_claimed' => 'boolean',
            'claimed_at' => 'datetime',
        ];
    }

    // Booking status constants
    const STATUS_INQUIRY = 'inquiry';
    const STATUS_COUNTER_OFFERED = 'counter_offered'; 
    const STATUS_PENDING_PAYMENT = 'pending_payment';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function brandUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'brand_user_id');
    }

    public function brandWorkspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'brand_workspace_id');
    }

    /**
     * Handle guest checkout completion and user/workspace creation
     */
    public static function handlePostPayment(array $session): self
    {
        $email = $session['customer_details']['email'];
        $name = $session['customer_details']['name'];

        // 1. Find or Create User
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name, 
                'password' => bcrypt(\Illuminate\Support\Str::random(32))
            ]
        );

        // 2. Find or Create the BRAND Workspace
        // A user can have many workspaces (one for creating, one for buying)
        $workspace = $user->workspaces()
            ->where('type', 'brand')
            ->first();

        if (!$workspace) {
            $workspace = $user->workspaces()->create([
                'name' => $name . "'s Brand Space",
                'type' => 'brand',
                'personal_team' => true,
            ]);
        }

        // 3. Finalize the Booking
        $booking = static::where('stripe_session_id', $session['id'])->first();
        $booking->update([
            'brand_user_id' => $user->id,
            'brand_workspace_id' => $workspace->id,
            'status' => static::STATUS_CONFIRMED,
        ]);

        return $booking;
    }

    /**
     * Scope for inquiry bookings
     */
    public function scopeInquiries(Builder $query): Builder
    {
        return $query->where('status', static::STATUS_INQUIRY);
    }

    /**
     * Scope for confirmed bookings
     */
    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', static::STATUS_CONFIRMED);
    }

    /**
     * Check if booking is an inquiry
     */
    public function isInquiry(): bool
    {
        return $this->status === static::STATUS_INQUIRY;
    }

    /**
     * Check if booking is confirmed/paid
     */
    public function isConfirmed(): bool
    {
        return $this->status === static::STATUS_CONFIRMED;
    }

    /**
     * Get the guest's full contact information
     */
    public function getGuestContactAttribute(): string
    {
        $parts = array_filter([
            $this->guest_name,
            $this->guest_company,
            $this->guest_email
        ]);
        
        return implode(' • ', $parts);
    }
}
