<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'provider',
        'provider_account_id',
        'provider_data',
        'is_active',
        'is_verified',
        'verified_at',
        'platform_fee_percentage',
    ];

    protected function casts(): array
    {
        return [
            'provider_data' => 'array',
            'is_active' => 'boolean',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
            'platform_fee_percentage' => 'decimal:4',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeForProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    // Helper methods
    public function isStripe(): bool
    {
        return $this->provider === 'stripe';
    }

    public function isPayPal(): bool
    {
        return $this->provider === 'paypal';
    }

    public function canReceivePayments(): bool
    {
        return $this->is_active && $this->is_verified && $this->provider_account_id;
    }

    public function markAsVerified(): void
    {
        $this->update([
            'is_verified' => true,
            'verified_at' => now(),
        ]);
    }
}
