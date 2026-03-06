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

    public function canTransitionTo(SlotStatus $status): bool
    {
        return $this->status->canTransitionTo($status);
    }
}
