<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
